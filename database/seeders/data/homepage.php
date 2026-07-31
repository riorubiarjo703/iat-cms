<?php

/**
 * SCBD reference content.
 *
 * English copy transcribed from the `data-i18n` elements of the reference
 * markup; Indonesian and Chinese from the reference dictionary. District and
 * facility copy exists in English only in the reference and falls back at
 * render time.
 *
 * `image` values are `ReferenceImageFetcher` slot names, not paths.
 */
return [
    'content' => [
        'brand_sub' => [
            'en' => 'Danayasa Arthatama',
            'id' => 'Danayasa Arthatama',
            'cn' => 'Danayasa Arthatama',
        ],
        'hero_line' => [
            'en' => "A district\nthat never\nclocks out",
            'id' => "Kawasan\nyang tak\npernah tidur",
            'cn' => "永不\n停歇的\n商务区",
        ],
        'hero_sub' => [
            'en' => 'Forty-five hectares in the middle of Jakarta where offices, hotels, retail and public space run as one address — Sudirman Central Business District.',
            'id' => 'Empat puluh lima hektar di jantung Jakarta tempat perkantoran, hotel, ritel dan ruang publik berjalan sebagai satu alamat — Sudirman Central Business District.',
            'cn' => '雅加达中心四十五公顷的土地，写字楼、酒店、零售与公共空间同属一个地址——苏迪曼中央商务区。',
        ],
        'about_heading' => [
            'en' => 'Built by Danayasa Arthatama. Run like a city.',
            'id' => 'Dibangun Danayasa Arthatama. Dikelola seperti sebuah kota.',
            'cn' => 'Danayasa Arthatama 开发，以城市方式运营。',
        ],
        'about_body' => [
            'en' => 'PT Danayasa Arthatama developed and still operates SCBD as a single, coordinated district — masterplanned infrastructure, its own security and fire service, its own clinic, its own parks. Tenants get a business address; the city gets a piece of urban fabric that works.',
            'id' => 'PT Danayasa Arthatama membangun dan mengelola SCBD sebagai satu kawasan terpadu — infrastruktur terencana, unit keamanan dan pemadam sendiri, klinik sendiri, taman sendiri.',
            'cn' => 'PT Danayasa Arthatama 开发并持续运营 SCBD：统一规划的基础设施、自有的安保与消防、自有诊所与公园。',
        ],
        'about_cta_label' => [
            'en' => 'Read the company profile',
            'id' => 'Baca profil perusahaan',
            'cn' => '阅读公司简介',
        ],
        'district_heading' => [
            'en' => "Everything inside\none walk",
            'id' => "Semua dalam\nsatu langkah",
            'cn' => "一步之内\n皆是所需",
        ],
        'district_body' => [
            'en' => 'Scroll sideways through the places that make up the district — towers, hotels, galleries and the open ground between them.',
            'id' => 'Geser ke samping untuk menyusuri tempat-tempat yang membentuk kawasan ini.',
            'cn' => '横向滚动，浏览构成这一园区的建筑与场所。',
        ],
        'facilities_heading' => [
            'en' => "Services that\nrun underneath",
            'id' => "Layanan yang\nbekerja di balik layar",
            'cn' => "看不见的\n运营支撑",
        ],
        'facilities_body' => [
            'en' => 'A district only feels effortless when the infrastructure is deliberate. These four are operated in-house, around the clock.',
            'id' => 'Kawasan terasa mudah hanya bila infrastrukturnya disengaja. Empat layanan ini dikelola sendiri, sepanjang waktu.',
            'cn' => '园区的从容源自刻意的基础设施。以下四项均由内部团队全天候运营。',
        ],
        'news_heading' => [
            'en' => "Latest from\nthe district",
            'id' => "Kabar terbaru\ndari kawasan",
            'cn' => "园区\n最新动态",
        ],
        'news_cta_label' => [
            'en' => 'All news',
            'id' => 'Semua berita',
            'cn' => '全部新闻',
        ],
        'contact_heading' => [
            'en' => "Take an address\nin the district",
            'id' => "Ambil alamat\ndi kawasan ini",
            'cn' => "在此\n落址",
        ],
        'marquee_text' => [
            'en' => 'Offices — Hotels — Retail — Residences — Public Realm',
            'id' => 'Perkantoran — Hotel — Ritel — Residensial — Ruang Publik',
            'cn' => '写字楼 — 酒店 — 零售 — 住宅 — 公共空间',
        ],
        'about_cta_url' => '/pages/company-profile',
        // The reference publishes no email address. Left null rather than inventing one.
        'contact_email' => null,
        'contact_phone' => '+62 (21) 515-2390',
        'contact_address' => "Jl. Jenderal\nSudirman\nKav 52–53",
        'hero_image_slot' => 'hero1',
        'about_image_slot' => 'towers',
    ],

    'menu' => [
        ['label' => ['en' => 'Company', 'id' => 'Perusahaan', 'cn' => '公司'], 'url' => '#about', 'sort' => 1],
        ['label' => ['en' => 'District', 'id' => 'Kawasan', 'cn' => '园区'], 'url' => '#district', 'sort' => 2],
        ['label' => ['en' => 'Facilities', 'id' => 'Fasilitas', 'cn' => '设施'], 'url' => '#facilities', 'sort' => 3],
        ['label' => ['en' => 'News', 'id' => 'Berita', 'cn' => '新闻'], 'url' => '#news', 'sort' => 4],
        ['label' => ['en' => 'Leasing enquiry', 'id' => 'Ajukan sewa', 'cn' => '租赁咨询'], 'url' => '#contact', 'sort' => 5, 'is_cta' => true],
    ],

    'places' => [
        ['title' => ['en' => 'The towers'], 'caption' => ['en' => 'Grade A office'], 'image_slot' => 'offices', 'sort' => 1],
        ['title' => ['en' => 'Places of interest'], 'caption' => ['en' => 'Hospitality & retail'], 'image_slot' => 'hospitality', 'sort' => 2],
        ['title' => ['en' => 'The public realm'], 'caption' => ['en' => 'Open ground'], 'image_slot' => 'publicrealm', 'sort' => 3],
    ],

    'facilities' => [
        [
            'title' => ['en' => 'Fire & emergency'],
            'body' => ['en' => 'A dedicated district fire station with its own appliances and crew, minutes from every tower lobby.'],
            'image_slot' => 'fireservice',
            'sort' => 1,
        ],
        [
            'title' => ['en' => 'District clinic'],
            'body' => ['en' => 'On-site medical care for the working population of the district, open through business hours and on call after them.'],
            'image_slot' => 'clinic',
            'sort' => 2,
        ],
        [
            'title' => ['en' => 'Security & access'],
            'body' => ['en' => 'One command centre covering perimeter, parking and public space — a single chain of responsibility across all 45 hectares.'],
            'image_slot' => 'security',
            'sort' => 3,
        ],
        [
            'title' => ['en' => 'Transport & parking'],
            'body' => ['en' => "Structured parking, shuttle circulation and direct access to the Sudirman corridor's transit spine."],
            'image_slot' => 'transport',
            'sort' => 4,
        ],
    ],

    'stats' => [
        ['label' => ['en' => 'Hectares masterplanned'], 'value' => 45, 'suffix' => null, 'format' => 'thousands', 'sort' => 1],
        ['label' => ['en' => 'Established'], 'value' => 1987, 'suffix' => null, 'format' => 'plain', 'sort' => 2],
        ['label' => ['en' => 'District security & response'], 'value' => 24, 'suffix' => '/7', 'format' => 'thousands', 'sort' => 3],
    ],

    'settings' => [
        'site_name' => 'SCBD',
        'default_locale' => 'en',
        'available_locales' => ['en', 'id', 'cn'],
        'meta_title' => [
            'en' => 'SCBD — Sudirman Central Business District',
            'id' => 'SCBD — Sudirman Central Business District',
            'cn' => 'SCBD — 苏迪曼中央商务区',
        ],
        'meta_description' => [
            'en' => 'Forty-five hectares in the middle of Jakarta where offices, hotels, retail and public space run as one address.',
            'id' => 'Empat puluh lima hektar di jantung Jakarta tempat perkantoran, hotel, ritel dan ruang publik berjalan sebagai satu alamat.',
            'cn' => '雅加达中心四十五公顷的土地，写字楼、酒店、零售与公共空间同属一个地址。',
        ],
    ],
];
