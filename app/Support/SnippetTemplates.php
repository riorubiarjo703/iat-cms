<?php

namespace App\Support;

use App\Enums\SnippetPosition;
use App\Enums\SnippetType;

/**
 * Starting points for the common tracking integrations.
 *
 * Each ships with a placeholder id rather than a real one, which is why
 * applying a template creates switched-off records — see
 * ListCodeSnippets::applyTemplate().
 *
 * @phpstan-type Template array{label: string, description: string, icon: string, snippets: array<int, array<string, mixed>>}
 */
final class SnippetTemplates
{
    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        return [
            'gtm' => [
                'label' => 'Google Tag Manager',
                'description' => 'Manage all your tags in one place. Creates two snippets (head + body start).',
                'icon' => 'heroicon-o-tag',
                'snippets' => [
                    [
                        'name' => 'Google Tag Manager (head)',
                        'type' => SnippetType::Script,
                        'position' => SnippetPosition::Head,
                        'priority' => 1,
                        'code' => <<<'HTML'
<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','GTM-XXXXXXX');</script>
<!-- End Google Tag Manager -->
HTML,
                    ],
                    [
                        'name' => 'Google Tag Manager (body)',
                        'type' => SnippetType::Html,
                        'position' => SnippetPosition::BodyStart,
                        'priority' => 1,
                        'code' => <<<'HTML'
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-XXXXXXX"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
HTML,
                    ],
                ],
            ],

            'ga4' => [
                'label' => 'Google Analytics 4',
                'description' => 'Use if you need custom GA4 configuration.',
                'icon' => 'heroicon-o-chart-bar',
                'snippets' => [
                    [
                        'name' => 'Google Analytics 4',
                        'type' => SnippetType::Script,
                        'position' => SnippetPosition::Head,
                        'priority' => 5,
                        'code' => <<<'HTML'
<script async src="https://www.googletagmanager.com/gtag/js?id=G-XXXXXXXXXX"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'G-XXXXXXXXXX');
</script>
HTML,
                    ],
                ],
            ],

            'meta_pixel' => [
                'label' => 'Meta / Facebook Pixel',
                'description' => 'Track conversions and build audiences for Meta ads.',
                'icon' => 'heroicon-o-share',
                'snippets' => [
                    [
                        'name' => 'Meta Pixel',
                        'type' => SnippetType::Script,
                        'position' => SnippetPosition::Head,
                        'priority' => 5,
                        'code' => <<<'HTML'
<!-- Meta Pixel Code -->
<script>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', 'YOUR_PIXEL_ID');
fbq('track', 'PageView');
</script>
<!-- End Meta Pixel Code -->
HTML,
                    ],
                ],
            ],

            'crisp' => [
                'label' => 'Crisp Chat',
                'description' => 'Add live chat widget to your website.',
                'icon' => 'heroicon-o-chat-bubble-left-right',
                'snippets' => [
                    [
                        'name' => 'Crisp Chat',
                        'type' => SnippetType::Script,
                        'position' => SnippetPosition::BodyEnd,
                        'priority' => 50,
                        'code' => <<<'HTML'
<script type="text/javascript">
  window.$crisp=[];
  window.CRISP_WEBSITE_ID="YOUR_WEBSITE_ID";
  (function(){
    var d=document,s=d.createElement("script");
    s.src="https://client.crisp.chat/l.js";
    s.async=1;
    d.getElementsByTagName("head")[0].appendChild(s);
  })();
</script>
HTML,
                    ],
                ],
            ],

            'custom_css' => [
                'label' => 'Custom CSS',
                'description' => 'Add custom styles to your site.',
                'icon' => 'heroicon-o-paint-brush',
                'snippets' => [
                    [
                        'name' => 'Custom CSS',
                        'type' => SnippetType::Style,
                        'position' => SnippetPosition::Head,
                        'priority' => 90,
                        'code' => "<style>\n/* Your custom styles */\n</style>",
                    ],
                ],
            ],

            'custom_js' => [
                'label' => 'Custom JavaScript',
                'description' => 'Add custom JavaScript to your site.',
                'icon' => 'heroicon-o-code-bracket',
                'snippets' => [
                    [
                        'name' => 'Custom JavaScript',
                        'type' => SnippetType::Script,
                        'position' => SnippetPosition::BodyEnd,
                        'priority' => 90,
                        'code' => "<script>\n// Your custom script\n</script>",
                    ],
                ],
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    public static function find(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }
}
