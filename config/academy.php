<?php

/*
|--------------------------------------------------------------------------
| Marketing copy for the public landing page
|--------------------------------------------------------------------------
|
| Everything a non-developer may want to reword lives here rather than in
| the Blade templates. The figures below are PLACEHOLDERS -- replace them
| with your real numbers before going live.
|
*/

return [

    'brand' => 'Traders Point Academy',

    /*
    |--------------------------------------------------------------------------
    | Streaming protection
    |--------------------------------------------------------------------------
    |
    | link_ttl   How long a freshly minted stream link may be used to START
    |            playback. This is NOT how long a lesson may be watched -- the
    |            controller grants the session a longer window once a valid
    |            link has been used, or a 60-second link would cut every
    |            lesson off after a minute.
    | max_grant  Ceiling on that window, whatever the lesson's length says.
    | chunk_bytes  Most we will ever answer in one response. Keeps a download
    |            manager from pulling the whole file on a single connection.
    |
    */
    'streaming' => [
        'link_ttl' => (int) env('STREAM_LINK_TTL', 60),
        'max_grant' => (int) env('STREAM_MAX_GRANT', 21600),
        'chunk_bytes' => (int) env('STREAM_CHUNK_BYTES', 2097152),
    ],

    'hero' => [
        'eyebrow' => 'NSE · BSE · Derivatives',
        'headline' => 'Master the Stock Market with Traders Point Academy',
        'subheadline' => 'Chart-by-chart lessons in technical analysis, options and swing trading — taught on real market setups, not textbook theory. The fundamentals are free to watch.',
    ],

    // Shown as a single mono band under the hero.
    'stats' => [
        ['value' => '2,400+', 'label' => 'Students enrolled'],
        ['value' => '6', 'label' => 'Market-active trainers'],
        ['value' => '100%', 'label' => 'Chart-based lessons'],
        ['value' => '92%', 'label' => 'Finish their first course'],
    ],

    'testimonials' => [
        [
            'quote' => 'I had watched a hundred free videos and still could not place a trade. The swing module gave me one checklist, and I finally stopped guessing my stop loss.',
            'name' => 'Rohit Deshmukh',
            'role' => 'Part-time trader, Pune',
        ],
        [
            'quote' => 'The option chain session is the only explanation of open interest that stayed with me. I now read the chain before the chart.',
            'name' => 'Sneha Iyer',
            'role' => 'Software engineer, Bengaluru',
        ],
        [
            'quote' => 'What I value is that nobody hands you a tip. They hand you the setup and make you mark the levels yourself.',
            'name' => 'Imran Qureshi',
            'role' => 'Small business owner, Hyderabad',
        ],
    ],

    'faqs' => [
        [
            'question' => 'Do I need any trading experience to start?',
            'answer' => 'No. Begin with the Basics of Stock Market videos, which are free. They cover what an exchange is, how orders are placed and how to open a demat account before any chart work starts.',
        ],
        [
            'question' => 'Are the free videos really free?',
            'answer' => 'Yes. Anything marked FREE plays in full without an account or a payment. Paid videos show their price on the card, and you pay once for lifetime access to that video.',
        ],
        [
            'question' => 'How long do I keep access after buying?',
            'answer' => 'A purchase does not expire. Sign in with the same account and the video stays in your library.',
        ],
        [
            'question' => 'What do I need to practise along with the lessons?',
            'answer' => 'Any charting platform you already use and a notebook. The lessons mark levels on daily and 15-minute charts, so a free charting account is enough to follow along.',
        ],
        [
            'question' => 'Do you give buy and sell calls?',
            'answer' => 'No. We are an education platform, not an advisory. We teach you to read a setup and size a position; we never tell you what to trade. Nothing here is investment advice.',
        ],
    ],

];
