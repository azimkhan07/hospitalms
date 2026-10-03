<?php

namespace App\Http\Livewire\Admins;

use App\Models\Settings as SettingModel;
use App\Models\SiteContent;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\WithFileUploads;

#[Layout('admins.layouts.app')]
class Settings extends Component
{
    use WithFileUploads;

    public $settings = [];
    public $logo;
    public $icon;
    public $hero_image;

    public array $fileKeys = ['logo', 'icon', 'hero_image'];

    public array $textareaKeys = ['description', 'about_title', 'hero_subtitle', 'emergency_text'];

    public array $defaults = [
        'title' => 'Metro Multi-Speciality Hospital',
        'tagline' => 'Multi-Speciality Hospital',
        'institution_mode' => 'hospital',
        'about_title' => 'About Us',
        'hero_title' => 'Compassionate care, advanced medicine',
        'hero_subtitle' => 'Book an appointment online or walk in — our specialists are here for you.',
        'emergency_title' => 'Emergency 24x7',
        'emergency_text' => 'Round-the-clock trauma and emergency care with on-call consultants.',
        'working_hours' => 'OPD 8:00 AM - 8:00 PM',
    ];

    public function mount(): void
    {
        foreach ($this->defaults as $key => $value) {
            SettingModel::firstOrCreate(['key' => $key], ['value' => $value]);
        }

        $this->settings = SettingModel::all()->pluck('value', 'key')->toArray();

        // Show the mode the tenant actually has, not a stale settings row.
        $this->settings['institution_mode'] = hms_institution_mode();
    }

    public function updateSettings()
    {
        $this->validate([
            'settings.title' => 'nullable|string|max:120',
            'settings.institution_mode' => 'required|in:clinic,hospital',
            'logo' => 'nullable|image|mimes:jpg,png,jpeg,svg,webp|max:2048',
            'icon' => 'nullable|image|mimes:jpg,png,jpeg,svg,webp|max:2048',
            'hero_image' => 'nullable|image|mimes:jpg,png,jpeg,webp|max:4096',
        ]);

        foreach ($this->settings as $key => $value) {
            if (in_array($key, $this->fileKeys, true)) {
                continue;
            }

            // The tenant row owns the institution mode (the Super Admin picks it
            // during onboarding), so writing it to settings here created a second
            // source of truth that role/module gating never looked at.
            if ($key === 'institution_mode') {
                auth()->user()?->tenant?->update(['mode' => $value]);
                SettingModel::updateOrCreate(['key' => $key], ['value' => $value]);

                continue;
            }

            SettingModel::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        foreach ($this->fileKeys as $key) {
            if ($this->{$key}) {
                $path = $this->{$key}->store('uploads', 'public');
                SettingModel::updateOrCreate(['key' => $key], ['value' => $path]);
            }
        }

        SiteContent::flush();

        session()->flash('message', 'Settings updated successfully.');
        return redirect()->route('admin_settings');
    }

    public function render()
    {
        if (! hms_can('settings')) {
            abort(403);
        }

        return view('livewire.admins.settings', [
            'modes' => config('hms.modes', []),
            'fileKeys' => $this->fileKeys,
            'textareaKeys' => $this->textareaKeys,
        ]);
    }
}
