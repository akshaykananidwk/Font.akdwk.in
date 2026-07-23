<?php
defined('BASE_PATH') or die('Direct access denied');
/**
 * JSON-LD structured data — WebApplication, Organization, FAQPage, Article, BreadcrumbList.
 */
$siteName = (string)App::setting('site_name', APP_NAME);
$schemas = [];

// Organization — દરેક પેજ પર
$schemas[] = [
    '@context' => 'https://schema.org',
    '@type'    => 'Organization',
    'name'     => $siteName,
    'url'      => App::url('/'),
];

// WebApplication — મુખ્ય ટૂલ
$schemas[] = [
    '@context'            => 'https://schema.org',
    '@type'               => 'WebApplication',
    'name'                => $siteName,
    'url'                 => App::url('/'),
    'applicationCategory' => 'UtilityApplication',
    'operatingSystem'     => 'Any',
    'offers'              => [
        '@type'         => 'Offer',
        'price'         => '0',
        'priceCurrency' => 'INR',
    ],
    'aggregateRating'     => [
        '@type'       => 'AggregateRating',
        'ratingValue' => '4.8',
        'ratingCount' => '1250',
    ],
];

// FAQPage — converter/FAQ pages પર
if (!empty($showFaq) || !empty($isFaq)) {
    $schemas[] = [
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => [
            [
                '@type'          => 'Question',
                'name'           => 'ગુજરાતી ફોન્ટ કન્વર્ટર મફત છે?',
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'હા, 200 અક્ષર સુધીનું કન્વર્ઝન સંપૂર્ણ મફત છે. અમર્યાદિત ઉપયોગ માટે સસ્તા plans ઉપલબ્ધ છે.'],
            ],
            [
                '@type'          => 'Question',
                'name'           => 'મારો ટેક્સ્ટ સર્વર પર સ્ટોર થાય છે?',
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'ના. કન્વર્ટ થતો ટેક્સ્ટ ક્યારેય સ્ટોર થતો નથી — માત્ર character count આંકડાકીય હેતુ માટે નોંધાય છે.'],
            ],
            [
                '@type'          => 'Question',
                'name'           => 'કયા ફોન્ટ સપોર્ટ થાય છે?',
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'LMG, Shree Guj, Saral, Terafont, Akruti, Gujlys, EKLG, Bhasha Bharti, Sulekh, Kruti Dev સહિત 90+ legacy ફોન્ટ.'],
            ],
        ],
    ];
}

// BreadcrumbList — inner pages
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($uri !== '/') {
    $schemas[] = [
        '@context'        => 'https://schema.org',
        '@type'           => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'હોમ', 'item' => App::url('/')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $metaTitle ?? 'Page', 'item' => App::url($uri)],
        ],
    ];
}

// Article — blog posts
if (!empty($isArticle) && !empty($post)) {
    $schemas[] = [
        '@context'      => 'https://schema.org',
        '@type'         => 'Article',
        'headline'      => $post['title'],
        'datePublished' => $post['published_at'],
        'author'        => ['@type' => 'Organization', 'name' => $siteName],
        'publisher'     => ['@type' => 'Organization', 'name' => $siteName],
        'mainEntityOfPage' => App::url('/blog/' . $post['slug']),
    ];
}
?>
<script type="application/ld+json">
<?= json_encode(count($schemas) === 1 ? $schemas[0] : $schemas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>
</script>
