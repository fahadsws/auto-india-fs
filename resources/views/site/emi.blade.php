@extends('site.layout')
@section('title', 'Car Loan EMI Calculator | '.\App\Models\Setting::get('site.name'))
@section('description', 'Free car loan EMI calculator. Enter loan amount, interest rate and tenure to see your monthly EMI, total interest and total payable instantly.')

@push('head')
<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'WebApplication', 'name' => 'Car Loan EMI Calculator', 'url' => route('emi'), 'applicationCategory' => 'FinanceApplication', 'operatingSystem' => 'Any', 'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'INR']], JSON_UNESCAPED_SLASHES) !!}</script>
@php($faq = [
  ['q' => 'How is car loan EMI calculated?', 'a' => 'EMI = P × r × (1+r)^n / ((1+r)^n − 1), where P is the loan amount, r is the monthly interest rate (annual rate ÷ 12 ÷ 100) and n is the number of monthly instalments.'],
  ['q' => 'Does a longer tenure reduce my EMI?', 'a' => 'Yes, a longer tenure lowers the monthly EMI, but you pay more total interest over the life of the loan.'],
  ['q' => 'What is a good down payment for a car loan?', 'a' => 'Most buyers pay 10–20% of the on-road price upfront. A larger down payment reduces the loan amount, EMI and total interest.'],
])
<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => collect($faq)->map(fn ($f) => ['@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']]])->values()->all()], JSON_UNESCAPED_SLASHES) !!}</script>
@endpush

@section('content')
<div class="phead"><div class="w"><h1 class="h">Car Loan EMI Calculator</h1><p>Plan your car purchase — see your monthly EMI, interest and total payable in seconds.</p></div></div>
@include('site.partials.ad-horizontal', ['ads' => $homeSettings->adsFor('horizontal')])

<div class="w"><div class="article-wrap emi-wrap">
  <div>
    <div class="aside-box emi" id="emi" data-min="{{ $cfg['min'] }}" data-max="{{ $cfg['max'] }}">
      <div class="emi-grid">
        <div class="emi-in">
          <div class="emi-f"><label>Loan Amount: <b>₹<span id="vAmt"></span></b></label>
            <input type="range" id="rAmt" min="{{ $cfg['min'] }}" max="{{ $cfg['max'] }}" step="10000" value="{{ $cfg['amount'] }}" aria-label="Loan amount">
            <input type="number" id="nAmt" min="{{ $cfg['min'] }}" max="{{ $cfg['max'] }}" step="10000" value="{{ $cfg['amount'] }}" inputmode="numeric"></div>
          <div class="emi-f"><label>Rate of Interest (p.a.): <b><span id="vRate"></span>%</b></label>
            <input type="range" id="rRate" min="1" max="30" step="0.1" value="{{ $cfg['rate'] }}" aria-label="Interest rate">
            <input type="number" id="nRate" min="1" max="30" step="0.1" value="{{ $cfg['rate'] }}" inputmode="decimal"></div>
          <div class="emi-f"><label>Loan Tenure: <b><span id="vTen"></span> Years</b></label>
            <input type="range" id="rTen" min="1" max="10" step="1" value="{{ $cfg['tenure'] }}" aria-label="Loan tenure in years">
            <input type="number" id="nTen" min="1" max="10" step="1" value="{{ $cfg['tenure'] }}" inputmode="numeric"></div>
        </div>
        <div class="emi-out">
          <p class="emi-big">EMI: <b>₹<span id="oEmi"></span></b></p>
          <p>Interest Payable: <b>₹<span id="oInt"></span></b></p>
          <p>Total Payable: <b>₹<span id="oTot"></span></b></p>
          <div class="emi-chart">
            <svg viewBox="0 0 120 120" role="img" aria-label="Principal versus interest">
              <circle cx="60" cy="60" r="45" fill="none" stroke="#fb9e1e" stroke-width="22"/>
              <circle id="dPrin" cx="60" cy="60" r="45" fill="none" stroke="#24a69a" stroke-width="22" stroke-dasharray="0 999" transform="rotate(-90 60 60)"/>
            </svg>
            <ul class="emi-legend"><li><i style="background:#fb9e1e"></i>Interest Payable</li><li><i style="background:#24a69a"></i>Principal Loan Amount</li></ul>
          </div>
        </div>
      </div>
    </div>

    <div class="prose">
      <h2>How to use the car loan EMI calculator</h2>
      <p>Move the sliders or type exact values for the loan amount, interest rate and tenure. The EMI, total interest and total payable update instantly, so you can compare different loan options before you visit a dealership.</p>
    </div>
    <h2 style="margin-top:28px">Frequently asked questions</h2>
    @foreach ($faq as $f)<details class="aside-box" style="padding:14px 18px;margin-bottom:10px"><summary style="font-weight:700;cursor:pointer">{{ $f['q'] }}</summary><p class="m" style="margin:10px 0 0">{{ $f['a'] }}</p></details>@endforeach
  </div>

  <aside>
    <div class="aside-box emi-lead">@include('site.partials.lead-form', ['emiContext' => true])</div>
    @include('site.partials.ad-vertical', ['page' => 'emi'])
    @include('site.partials.latest-news', ['articles' => $latest])
  </aside>
</div></div>

<script>
(() => {
  const $ = id => document.getElementById(id), fmt = n => Math.round(n).toLocaleString('en-IN');
  const link = (r, n) => { r.addEventListener('input', () => { n.value = r.value; calc(); }); n.addEventListener('input', () => { if (n.value !== '') { r.value = n.value; calc(); } }); };
  link($('rAmt'), $('nAmt')); link($('rRate'), $('nRate')); link($('rTen'), $('nTen'));
  function calc() {
    const clamp = (el, d) => Math.min(Math.max(parseFloat(el.value) || d, +el.min), +el.max);
    const P = clamp($('nAmt'), +$('rAmt').min), R = clamp($('nRate'), 10), Y = clamp($('nTen'), 5);
    const r = R / 1200, n = Math.round(Y) * 12;
    const emi = r ? P * r * Math.pow(1 + r, n) / (Math.pow(1 + r, n) - 1) : P / n;
    const tot = emi * n, int = tot - P, c = 2 * Math.PI * 45;
    $('vAmt').textContent = fmt(P); $('vRate').textContent = R; $('vTen').textContent = Math.round(Y);
    $('oEmi').textContent = fmt(emi); $('oInt').textContent = fmt(int); $('oTot').textContent = fmt(tot);
    $('dPrin').setAttribute('stroke-dasharray', (c * P / tot) + ' ' + c);
    const h = $('emiContext'); if (h) h.value = `Amount ₹${fmt(P)}, rate ${R}%, tenure ${Math.round(Y)} yrs, EMI ₹${fmt(emi)}`;
  }
  calc();
})();
</script>
@endsection
