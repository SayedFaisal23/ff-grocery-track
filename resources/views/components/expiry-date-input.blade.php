@props(['value' => '', 'pickerValue' => ''])

<div class="date-input-control" data-expiry-date-input>
    <input
        type="text"
        name="tarikh_luput"
        id="tarikh_luput"
        class="form-control @error('tarikh_luput') is-invalid @enderror"
        value="{{ $value }}"
        placeholder="dd/mm/yyyy"
        inputmode="numeric"
        pattern="\d{2}/\d{2}/\d{4}"
        autocomplete="off"
    >
    <input type="date" class="date-picker-proxy" value="{{ $pickerValue }}" tabindex="-1" aria-hidden="true">
    <button type="button" class="date-picker-trigger" aria-label="Buka kalendar tarikh luput">
        <i class="fa-solid fa-calendar-days" aria-hidden="true"></i>
    </button>
</div>

@once
<script>
    (() => {
        const toIsoDate = (value) => {
            const match = value.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);

            return match ? `${match[3]}-${match[2]}-${match[1]}` : '';
        };

        const toDisplayDate = (value) => {
            const match = value.match(/^(\d{4})-(\d{2})-(\d{2})$/);

            return match ? `${match[3]}/${match[2]}/${match[1]}` : '';
        };

        const initialiseDatePickers = () => {
            document.querySelectorAll('[data-expiry-date-input]').forEach((control) => {
                if (control.dataset.datePickerReady) return;

                control.dataset.datePickerReady = 'true';

                const textInput = control.querySelector('input[type="text"]');
                const picker = control.querySelector('input[type="date"]');
                const trigger = control.querySelector('.date-picker-trigger');

                trigger.addEventListener('click', () => {
                    const isoDate = toIsoDate(textInput.value);

                    if (isoDate) picker.value = isoDate;

                    try {
                        picker.showPicker();
                    } catch {
                        picker.focus();
                        picker.click();
                    }
                });

                picker.addEventListener('change', () => {
                    textInput.value = toDisplayDate(picker.value);
                    textInput.dispatchEvent(new Event('change', { bubbles: true }));
                });
            });
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initialiseDatePickers, { once: true });
        } else {
            initialiseDatePickers();
        }
    })();
</script>
@endonce
