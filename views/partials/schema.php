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
                'name'           => 'Is the Gujarati Font Converter free?',
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Yes, converting up to 200 characters is completely free. Affordable plans are available for unlimited use.'],
            ],
            [
                '@type'          => 'Question',
                'name'           => 'Is my text stored on the server?',
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'No. The text you convert is never stored — only the character count is recorded for statistical purposes.'],
            ],
            [
                '@type'          => 'Question',
                'name'           => 'Which fonts are supported?',
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => '90+ legacy fonts including LMG, Shree Guj, Saral, Terafont, Akruti, Gujlys, EKLG, Bhasha Bharti, Sulekh, and Kruti Dev.'],
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
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => App::url('/')],
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
