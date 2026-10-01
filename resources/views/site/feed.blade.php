{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
  <title>{{ \App\Models\Setting::get('site.name') }}</title>
  <link>{{ route('home') }}</link>
  <description>{{ \App\Models\Setting::get('site.tagline') }}</description>
  <language>en-in</language>
  <atom:link href="{{ route('feed') }}" rel="self" type="application/rss+xml"/>
@foreach ($articles as $a)
  <item>
    <title>{{ $a->title }}</title>
    <link>{{ $a->url }}</link>
    <guid isPermaLink="true">{{ $a->url }}</guid>
    <pubDate>{{ $a->published_at?->toRfc2822String() }}</pubDate>
    @if ($a->category)<category>{{ $a->category->name }}</category>@endif
    <description>{{ $a->excerpt }}</description>
    @if ($a->image_path)<enclosure url="{{ $a->image_url }}" type="image/jpeg" length="0"/>@endif
  </item>
@endforeach
</channel>
</rss>
