@extends('admins.layouts.app')

@section('admin_content')
    <div class="box box-primary">
        <div class="box-header d-flex align-items-center justify-content-between">
            <h3 class="box-title">
                <i class="fas fa-video text-info mr-1"></i>
                {{ $meeting->title }}
                <small class="text-muted ml-2" style="font-size:11.5px">
                    {{ $meeting->scheduled_at->format('d M Y, h:i A') }}
                    &middot; {{ $meeting->duration_minutes }} min
                    @if ($meeting->host) &middot; host: {{ $meeting->host->name }} @endif
                </small>
            </h3>
            <div class="d-flex align-items-center">
                @if ($isHost)
                    <button type="button" id="rec-btn" class="btn btn-sm btn-outline-danger mr-2">
                        <i class="fas fa-circle"></i>
                        <span id="rec-label">{{ $meeting->recording_enabled ? 'Stop recording' : 'Start recording' }}</span>
                    </button>
                    <span class="badge badge-danger mr-2" id="rec-live" style="display:none">REC</span>
                @endif
                <a href="{{ route('admin_meetings') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
        <div class="box-body p-0">
            <div id="jitsi-container" style="height:72vh;width:100%;background:#0b3c66"></div>
        </div>
    </div>

    <script src="https://meet.jit.si/external_api.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof JitsiMeetExternalAPI === 'undefined') {
                document.getElementById('jitsi-container').innerHTML =
                    '<div class="p-4 text-white">Video service is unreachable right now. ' +
                    'Use the meeting link shared with you, or try again shortly.</div>';
                return;
            }

            var api = new JitsiMeetExternalAPI('meet.jit.si', {
                roomName: @json($meeting->roomName()),
                parentNode: document.getElementById('jitsi-container'),
                userInfo: { displayName: @json($displayName) },
                configOverwrite: {
                    prejoinPageEnabled: false,
                    disableDeepLinking: true,
                    startWithAudioMuted: true,
                    startWithVideoMuted: false
                },
                interfaceConfigOverwrite: { SHOW_JITSI_WATERMARK: false }
            });

            var isHost = @json($isHost);
            var wantsRecording = @json((bool) $meeting->recording_enabled);
            var recBtn = document.getElementById('rec-btn');
            var recLabel = document.getElementById('rec-label');
            var recLive = document.getElementById('rec-live');

            function beginRecording() {
                api.executeCommand('startRecording', { mode: 'local', transcription: false });
                if (recLabel) recLabel.textContent = 'Stop recording';
                if (recLive) recLive.style.display = 'inline-block';
            }

            if (isHost && wantsRecording) {
                api.addEventListener('videoConferenceJoined', beginRecording);
            }

            if (recBtn) {
                recBtn.addEventListener('click', function () {
                    var turningOn = recLabel.textContent.trim().toLowerCase().indexOf('start') === 0;
                    api.executeCommand(turningOn ? 'startRecording' : 'stopRecording', { mode: 'local' });
                    recLabel.textContent = turningOn ? 'Stop recording' : 'Start recording';
                    if (recLive) recLive.style.display = turningOn ? 'inline-block' : 'none';

                    fetch(@json(route('admin_meeting_recording', $meeting->id)), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ enabled: turningOn })
                    }).catch(function () {});
                });
            }
        });
    </script>
@endsection
