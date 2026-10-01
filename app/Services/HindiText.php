<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Hindi is the assistant's default language, but the database filters, knowledge search and intent rules understand
 * Roman/English words. This turns the Devanagari words that matter (fuel, budget, cities, brands, "show all"...) into
 * their English form so a Hindi message runs the same lookups. The ORIGINAL message is still what the AI sees.
 */
class HindiText
{
    private const DIGITS = ['०' => '0', '१' => '1', '२' => '2', '३' => '3', '४' => '4', '५' => '5', '६' => '6', '७' => '7', '८' => '8', '९' => '9'];

    /** Longest phrases first so "सेकंड हैंड" wins over "हैंड". */
    private const WORDS = [
        'सेकंड हैंड' => 'used', 'सेकण्ड हैण्ड' => 'used', 'पुरानी' => 'used', 'पुराना' => 'used', 'पुराने' => 'used', 'पुरानि' => 'used', 'यूज़्ड' => 'used', 'यूज्ड' => 'used',
        'नई गाड़ी' => 'new car', 'नयी गाड़ी' => 'new car', 'नई गाडी' => 'new car', 'नई कार' => 'new car', 'नयी कार' => 'new car', 'नया' => 'new', 'नई' => 'new', 'नयी' => 'new', 'नए' => 'new',
        'टेस्ट ड्राइव' => 'test drive', 'टेस्ट ड्राईव' => 'test drive', 'टेस्टड्राइव' => 'test drive', 'इंस्पेक्शन' => 'inspection', 'इन्स्पेक्शन' => 'inspection', 'निरीक्षण' => 'inspection',
        'पेट्रोल' => 'petrol', 'डीज़ल' => 'diesel', 'डीजल' => 'diesel', 'सीएनजी' => 'cng', 'इलेक्ट्रिक' => 'electric', 'हाइब्रिड' => 'hybrid',
        'ऑटोमैटिक' => 'automatic', 'ऑटोमेटिक' => 'automatic', 'आटोमैटिक' => 'automatic', 'मैनुअल' => 'manual', 'मैन्युअल' => 'manual',
        'करोड़' => 'crore', 'करोड' => 'crore', 'लाख' => 'lakh', 'हज़ार' => 'thousand', 'हजार' => 'thousand',
        'से कम' => 'under', 'के अंदर' => 'under', 'के अन्दर' => 'under', 'तक' => 'under', 'से ज़्यादा' => 'above', 'से ज्यादा' => 'above', 'से ऊपर' => 'above', 'के बीच' => 'between',
        'किलोमीटर' => 'km', 'किमी' => 'km', 'पहला मालिक' => 'first owner', 'फर्स्ट ओनर' => 'first owner', 'पहले मालिक' => 'first owner',
        'एसयूवी' => 'suv', 'हैचबैक' => 'hatchback', 'हेचबैक' => 'hatchback', 'सेडान' => 'sedan', 'एमयूवी' => 'muv', 'बाइक' => 'bike', 'ट्रक' => 'truck', 'स्कूटर' => 'scooter',
        'गाड़ियाँ' => 'cars', 'गाड़ियां' => 'cars', 'गाड़ी' => 'car', 'गाडी' => 'car', 'गाड़ियों' => 'cars', 'कारें' => 'cars', 'कार' => 'car', 'वाहन' => 'vehicle',
        'सबसे सस्ती' => 'cheapest', 'सबसे सस्ता' => 'cheapest', 'सस्ती' => 'cheapest', 'सस्ता' => 'cheapest', 'सबसे महंगी' => 'costliest', 'सबसे महँगी' => 'costliest',
        'कितनी' => 'how many', 'कितने' => 'how many', 'कीमत' => 'price', 'क़ीमत' => 'price', 'दाम' => 'price', 'प्राइस' => 'price', 'ईएमआई' => 'emi', 'लोन' => 'loan', 'फाइनेंस' => 'finance',
        'सारी' => 'sari', 'सारे' => 'sare', 'सभी' => 'sabhi', 'सब' => 'sab', 'पूरी' => 'puri', 'दिखाओ' => 'dikhao', 'दिखाइए' => 'dikhao', 'दिखाइये' => 'dikhao', 'दिखा' => 'dikha', 'दिखाना' => 'dikhao',
        'चाहिए' => 'chahiye', 'चाहिये' => 'chahiye', 'चाहता' => 'chahta', 'चाहती' => 'chahti', 'खरीदना' => 'kharidna', 'खरीदनी' => 'kharidna', 'लेनी' => 'leni', 'लेना' => 'lena',
        'पहली' => 'pehli', 'पहला' => 'pehli', 'दूसरी' => 'second', 'दूसरा' => 'second', 'तीसरी' => 'third', 'तीसरा' => 'third', 'आखिरी' => 'last', 'यही' => 'yahi', 'इसको' => 'isko', 'इसे' => 'ise', 'वाली' => 'wali', 'वाला' => 'wala',
        'शुरू से' => 'start over', 'नई खोज' => 'new search', 'कोई और' => 'koi aur', 'कुछ और' => 'kuch aur',
        'कहीं भी' => 'anywhere', 'कहीं' => 'anywhere', 'कोई भी' => 'any', 'कोई सीमा नहीं' => 'no limit', 'कोई लिमिट नहीं' => 'no limit',
        'नमस्ते' => 'namaste', 'नमस्कार' => 'namaskar', 'धन्यवाद' => 'dhanyavad', 'शुक्रिया' => 'shukriya', 'हाँ' => 'haan', 'हां' => 'haan', 'नहीं' => 'nahi',
        'पता' => 'address', 'संपर्क' => 'contact', 'नंबर' => 'phone', 'समय' => 'timings', 'सेवाएं' => 'services', 'ऑफर' => 'offers', 'वारंटी' => 'warranty', 'बीमा' => 'insurance', 'शोरूम' => 'showroom', 'डीलर' => 'dealer',
        'समाचार' => 'news', 'ख़बर' => 'news', 'खबर' => 'news', 'वीडियो' => 'video', 'तुलना' => 'compare', 'ब्रोशर' => 'brochure',
        // cities
        'दिल्ली' => 'Delhi', 'मुंबई' => 'Mumbai', 'मुम्बई' => 'Mumbai', 'बेंगलुरु' => 'Bengaluru', 'बैंगलोर' => 'Bengaluru', 'बेंगलूरु' => 'Bengaluru', 'चेन्नई' => 'Chennai', 'हैदराबाद' => 'Hyderabad',
        'कोलकाता' => 'Kolkata', 'पुणे' => 'Pune', 'पूना' => 'Pune', 'अहमदाबाद' => 'Ahmedabad', 'इंदौर' => 'Indore', 'जयपुर' => 'Jaipur', 'लखनऊ' => 'Lucknow', 'चंडीगढ़' => 'Chandigarh', 'नोएडा' => 'Noida',
        'गुरुग्राम' => 'Gurgaon', 'गुड़गांव' => 'Gurgaon', 'गाज़ियाबाद' => 'Ghaziabad', 'गाजियाबाद' => 'Ghaziabad', 'भोपाल' => 'Bhopal', 'नागपुर' => 'Nagpur', 'सूरत' => 'Surat', 'पटना' => 'Patna', 'कानपुर' => 'Kanpur',
        'वडोदरा' => 'Vadodara', 'नासिक' => 'Nashik', 'राजकोट' => 'Rajkot', 'कोच्चि' => 'Kochi', 'कोयंबटूर' => 'Coimbatore', 'विशाखापत्तनम' => 'Visakhapatnam', 'आगरा' => 'Agra', 'मेरठ' => 'Meerut', 'देहरादून' => 'Dehradun', 'रांची' => 'Ranchi',
        // brands
        'मारुति' => 'Maruti', 'मारुती' => 'Maruti', 'हुंडई' => 'Hyundai', 'हुंडा' => 'Hyundai', 'टाटा' => 'Tata', 'महिंद्रा' => 'Mahindra', 'होंडा' => 'Honda', 'टोयोटा' => 'Toyota', 'किआ' => 'Kia', 'रेनॉ' => 'Renault', 'रेनो' => 'Renault',
        'निसान' => 'Nissan', 'स्कोडा' => 'Skoda', 'फोक्सवैगन' => 'Volkswagen', 'फॉक्सवैगन' => 'Volkswagen', 'एमजी' => 'MG', 'जीप' => 'Jeep', 'फोर्ड' => 'Ford', 'बीएमडब्ल्यू' => 'BMW', 'ऑडी' => 'Audi', 'मर्सिडीज' => 'Mercedes',
        // models people say most
        'क्रेटा' => 'Creta', 'ब्रेज़ा' => 'Brezza', 'ब्रेजा' => 'Brezza', 'नेक्सॉन' => 'Nexon', 'स्विफ्ट' => 'Swift', 'बलेनो' => 'Baleno', 'वैगनआर' => 'WagonR', 'फॉर्च्यूनर' => 'Fortuner', 'स्कॉर्पियो' => 'Scorpio', 'थार' => 'Thar', 'पंच' => 'Punch', 'इनोवा' => 'Innova', 'वेन्यू' => 'Venue', 'सेल्टोस' => 'Seltos', 'अल्टो' => 'Alto', 'डिजायर' => 'Dzire', 'डिज़ायर' => 'Dzire', 'एक्सयूवी' => 'XUV',
    ];

    /** @var array<string,string> NFC-normalised phrase => English word */
    private static array $nfcMap = [];

    /** Composed (ड़) and decomposed (ड + nukta) Devanagari must match the same word. */
    private static function nfc(string $s): string
    {
        return class_exists(\Normalizer::class) ? (\Normalizer::normalize($s, \Normalizer::FORM_C) ?: $s) : $s;
    }

    public static function has(string $text): bool
    {
        return (bool) preg_match('/[\x{0900}-\x{097F}]/u', $text);
    }

    /** Devanagari -> English words for the rule/lookup layer. Text without Devanagari is returned unchanged. */
    public static function normalize(string $text): string
    {
        if (! self::has($text)) return $text;
        $t = strtr(self::nfc($text), self::DIGITS);
        static $re = null;
        if ($re === null) {
            $keys = array_keys(self::WORDS);
            usort($keys, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
            $re = '/(?<![\p{L}\p{M}])(?:'.implode('|', array_map(fn ($k) => preg_quote(self::nfc($k), '/'), $keys)).')(?![\p{L}\p{M}])/u';
            foreach (self::WORDS as $k => $v) self::$nfcMap[self::nfc($k)] = $v;
        }
        $t = preg_replace_callback($re, fn ($m) => ' '.(self::$nfcMap[$m[0]] ?? '').' ', $t);
        $t = preg_replace('/(\d)\s*से\s*(\d)/u', '$1 to $2', $t);                                                  // "5 से 8 lakh"
        $t = preg_replace('/(\d+(?:\.\d+)?\s*(?:lakh|crore|thousand|k|km))\s+under\b/u', 'under $1', $t);        // "8 lakh under" -> "under 8 lakh"
        return trim(preg_replace('/\s+/u', ' ', $t));
    }

    /** True when the message is plain English (so the assistant may answer in English instead of the Hindi default). */
    public static function isEnglish(string $text): bool
    {
        if (self::has($text)) return false;
        $t = Str::lower($text);
        if (preg_match('/\b(mujhe|muje|mere|mera|meri|chahiye|chaiye|chahie|kya|kaun|kitna|kitne|nahi|nhi|haan|aap|apna|mein|mai|hai|hain|karna|karni|dikhao|dikha|sare|saare|gaadi|gadi|batao|bataiye|wali|wala|purani|nayi|kal|parso|bhai|ji|acha|accha|theek|thik)\b/u', $t)) return false;
        return count(preg_split('/\s+/', trim($t))) >= 3 && preg_match('/\b(the|is|are|what|which|how|can|do|does|i|you|want|need|show|looking|price|best|for|with|in|my|me|please|have|tell|about)\b/', $t) === 1;
    }
}
