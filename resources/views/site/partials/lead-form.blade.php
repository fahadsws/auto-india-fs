{{-- "Get best offers" lead form. Optional: $emiContext (shows a hidden field that is saved with the lead). --}}
<section class="fx-lead" id="lead">
    <h3>Get best offers</h3>
    @if(session('lead_success'))
        <p class="fx-ok"><i class="ti ti-circle-check"></i> {{ session('lead_success') }}</p>
    @else
        <form method="POST" action="{{ route('lead.submit') }}">
            @csrf
            <input type="text" name="website" class="fx-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
            @isset($emiContext)<input type="hidden" name="emi_context" id="emiContext" value="">@endisset
            <input name="name" value="{{ old('name') }}" placeholder="Your name" required maxlength="120" autocomplete="name">
            <input name="phone" type="tel" inputmode="tel" value="{{ old('phone') }}" placeholder="Mobile number" required
                pattern="[+0-9 \-]{8,15}" autocomplete="tel">
            <select name="city" required>
                <option value="">Select city</option>
                @foreach(\App\Support\Filters::CITIES as $c)<option @selected(old('city') === $c)>{{ $c }}</option>@endforeach
            </select>
            @if($errors->any())<p class="fx-err">{{ $errors->first() }}</p>@endif
            <button type="submit">Get best offers</button>
        </form>
    @endif
</section>
