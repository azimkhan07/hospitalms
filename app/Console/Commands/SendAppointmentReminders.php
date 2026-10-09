<?php

namespace App\Console\Commands;

use App\Contracts\MessageSender;
use App\Models\appointment;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AppointmentReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Reminder sweep (PLAN notification scheduler): nudge the front desk in-app
 * and best-effort the patient over SMS/WhatsApp before an upcoming slot.
 * Only pending/confirmed visits inside the window qualify, and reminded_at
 * stamps each appointment the moment it is pinged so it fires exactly once.
 */
class SendAppointmentReminders extends Command
{
    /** patients.phone is free text, but every stored number is digits/dashes. */
    private const PHONE_RE = '/^[0-9+\-\s()]{7,20}$/';

    protected $signature = 'hms:send-appointment-reminders {--minutes=45 : How far ahead to remind}';

    protected $description = 'Remind staff and patients about appointments starting within the next N minutes';

    public function handle(): int
    {
        $now = now();
        $target = $now->copy()->addMinutes(max(1, (int) $this->option('minutes')));

        foreach (Tenant::orderBy('id')->get() as $tenant) {
            $count = $this->remindFor($tenant, $now, $target);
            $this->info("Reminded {$count} appointment(s) for {$tenant->name}.");
        }

        return self::SUCCESS;
    }

    /**
     * The tenant global scope only pins itself while somebody is signed in
     * (tests sign a staff user in before calling artisan), so drop it and
     * pin the facility by column -- zero ambiguity about which rows move.
     */
    private function remindFor(Tenant $tenant, $now, $target): int
    {
        $appointments = appointment::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->whereBetween('intime', [$now, $target])
            ->whereNull('reminded_at')
            ->with(['patient', 'doctor.employ'])
            ->get();

        if ($appointments->isEmpty()) {
            return 0;
        }

        $recipients = User::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->whereHas('role', fn ($r) => $r->whereIn('slug', ['receptionist', 'admin', 'moderator']))
            ->get();

        $count = 0;

        foreach ($appointments as $apt) {
            // Stamp first: a crash mid-run must never re-notify tomorrow.
            $apt->reminded_at = now();
            $apt->save();

            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new AppointmentReminder($apt));
            }

            $this->messagePatient($apt);

            $count++;
        }

        return $count;
    }

    /**
     * Best effort only: unconfigured messaging and dead gateways are
     * swallowed here so a reminder sweep can never crash the scheduler.
     */
    private function messagePatient(appointment $apt): void
    {
        $phone = trim((string) $apt->patient?->phone);

        if ($phone === '' || ! preg_match(self::PHONE_RE, $phone)) {
            return;
        }

        $doctor = optional($apt->doctor?->employ)->name;
        $when = $apt->intime->isToday() ? 'today' : 'on '.$apt->intime->format('D j M');
        $body = 'Reminder: your appointment with '.$doctor.' at '.config('app.name', 'HospitalMS')
            .' starts '.$apt->intime->format('H:i').' '.$when.'.';

        try {
            $sender = resolve(MessageSender::class);
            $sender->whatsapp($phone, $body);
            $sender->sms($phone, $body);
        } catch (\Throwable) {
            // Messaging is optional; a missing config must not kill the run.
        }
    }
}
