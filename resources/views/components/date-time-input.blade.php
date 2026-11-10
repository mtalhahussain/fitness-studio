@props(['value' => ''])
<div x-data="{
    value: @js($value),
    get date() { return this.value.slice(0, 10); },
    set date(date) { this.value = date + 'T' + (this.value.slice(11, 16) || '09:00'); },
    get hour() { return String(Number(this.value.slice(11, 13)) % 12 || 12); },
    get minute() { return this.value.slice(14, 16) || '00'; },
    get period() { return Number(this.value.slice(11, 13)) >= 12 ? 'PM' : 'AM'; },
    updateTime(hour, minute, period) {
        const hours = Number(hour) % 12 + (period === 'PM' ? 12 : 0);
        this.value = this.date + 'T' + String(hours).padStart(2, '0') + ':' + minute;
    }
}" x-modelable="value" {{ $attributes }}>
    <input type="date" class="form-input" aria-label="Date" x-model="date" required>
    <div style="display:flex;gap:6px;margin-top:6px">
        <select class="form-select" aria-label="Hour" :value="hour" @change="updateTime($event.target.value, minute, period)">
            @foreach(range(1, 12) as $hour)
                <option value="{{ $hour }}">{{ str_pad($hour, 2, '0', STR_PAD_LEFT) }}</option>
            @endforeach
        </select>
        <select class="form-select" aria-label="Minute" :value="minute" @change="updateTime(hour, $event.target.value, period)">
            @foreach(range(0, 59) as $minute)
                <option value="{{ str_pad($minute, 2, '0', STR_PAD_LEFT) }}">{{ str_pad($minute, 2, '0', STR_PAD_LEFT) }}</option>
            @endforeach
        </select>
        <select class="form-select" aria-label="AM or PM" :value="period" @change="updateTime(hour, minute, $event.target.value)">
            <option value="AM">AM</option>
            <option value="PM">PM</option>
        </select>
    </div>
    @if($attributes->has('name'))
        <input type="hidden" name="{{ $attributes->get('name') }}" :value="value">
    @endif
</div>
