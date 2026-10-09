<div class="box box-primary">
    @php $hospital = hms_tenant_brand()['name']; @endphp
    <div class="box-header d-flex align-items-center justify-content-between flex-wrap">
        <h3 class="box-title"><i class="fas fa-id-badge text-info mr-1"></i> ID Cards</h3>
        <div class="d-flex align-items-center">
            <input type="search" class="form-control form-control-sm mr-2" style="max-width:220px"
                placeholder="Search name or phone..." wire:model.live.debounce.300ms="search">
            <a class="btn btn-xs btn-primary"
                href="{{ route('admin_id_cards_print', array_filter(['role' => $role, 'search' => $search])) }}"
                target="_blank">
                <i class="fas fa-print"></i> Print all
            </a>
        </div>
    </div>

    <div class="box-body">
        <div class="mb-3 d-flex align-items-center flex-wrap">
            <label class="mb-0 mr-2" style="font-size:12px">Role</label>
            <select class="form-control form-control-sm mr-2" style="max-width:220px" wire:model.live="role">
                <option value="">All staff</option>
                @foreach ($roles as $r)
                    <option value="{{ $r->slug }}">{{ $r->name }}</option>
                @endforeach
            </select>
            <button type="button" class="btn btn-xs btn-outline-secondary" wire:click="$set('role', '')">
                Clear
            </button>
        </div>

        <div class="text-info" wire:loading>Loading..</div>

        <div class="row">
            @forelse ($cards as $card)
                <div class="col-md-6 col-lg-4 mb-3">
                    <div class="hms-idcard">
                        <div class="hms-idcard-ribbon">
                            <div class="hms-idcard-hospital">{{ $hospital }}</div>
                            <div class="hms-idcard-gold"></div>
                        </div>
                        <div class="hms-idcard-body">
                            <img src="{{ $card['photo'] }}" alt="{{ $card['name'] }}"
                                class="hms-idcard-photo">
                            <div class="hms-idcard-name">{{ $card['name'] }}</div>
                            <span class="hms-idcard-chip">{{ $card['role'] }}</span>
                            <div class="hms-idcard-meta">
                                {{ $card['department'] ?: 'General' }}
                                @if ($card['staff_code'])
                                    &middot; {{ $card['staff_code'] }}
                                @endif
                            </div>
                            <div class="hms-idcard-info">
                                <span><i class="fas fa-phone"></i> {{ $card['phone'] ?: '-' }}</span>
                                <span><i class="fas fa-envelope"></i> {{ $card['email'] }}</span>
                            </div>
                            <div class="hms-idcard-foot">If found return to: {{ $hospital }}</div>
                        </div>
                        <div class="hms-idcard-actions">
                            <a class="btn btn-xs btn-primary"
                                href="{{ route('admin_id_cards_print', ['user' => $card['id']]) }}" target="_blank">
                                <i class="fas fa-print"></i> Print card
                            </a>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12 text-center text-muted py-3">No staff match this filter.</div>
            @endforelse
        </div>
    </div>
    <style>
    .hms-idcard {
        border: 1px solid #e3ebf3;
        border-radius: 8px;
        overflow: hidden;
        background: #f6f9fc;
        max-width: 340px;
    }
    .hms-idcard-ribbon {
        background: #0b3c66;
        color: #fff;
        text-align: center;
        padding: 6px 8px 0;
    }
    .hms-idcard-hospital {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .4px;
    }
    .hms-idcard-gold {
        height: 2px;
        background: #c9a227;
        margin-top: 5px;
    }
    .hms-idcard-body {
        padding: 10px 12px;
        text-align: center;
    }
    .hms-idcard-photo {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #0f7fd4;
        background: #fff;
    }
    .hms-idcard-name {
        font-size: 15px;
        font-weight: 700;
        color: #0b3c66;
        margin-top: 6px;
    }
    .hms-idcard-chip {
        display: inline-block;
        background: #0f7fd4;
        color: #fff;
        font-size: 10px;
        padding: 1px 8px;
        border-radius: 10px;
        margin-top: 3px;
    }
    .hms-idcard-meta {
        font-size: 11px;
        color: #61748a;
        margin-top: 5px;
    }
    .hms-idcard-info {
        display: flex;
        flex-direction: column;
        font-size: 10px;
        color: #61748a;
        margin-top: 6px;
        gap: 1px;
    }
    .hms-idcard-foot {
        font-size: 9px;
        color: #61748a;
        border-top: 1px solid #e3ebf3;
        margin-top: 8px;
        padding-top: 5px;
    }
    .hms-idcard-actions {
        text-align: center;
        padding: 6px;
        background: #fff;
        border-top: 1px solid #e3ebf3;
    }
</style>
</div>
