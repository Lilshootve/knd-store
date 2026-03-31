<?php

require_once __DIR__ . '/pricing.php';

// KND Store - Fuente única de datos de productos (key = slug)

$PRODUCTS = [

    'formateo-limpieza-pc' => [

        'id' => 1,

        'nombre' => 'PC Format & Cleanup (Remote)',

        'descripcion' => 'Restore your PC performance from the comfort of your ship.<br><br>Includes:<br>• Full system format<br>• Clean Windows installation<br>• Updated driver installation<br>• Performance optimization<br>• Temporary file cleanup<br>• Basic security setup',

        'precio' => 10.00,

        'imagen' => 'assets/images/productos/formateo-limpieza-pc-remoto.png',

        'categoria' => 'tecnologia',

        'slug' => 'formateo-limpieza-pc',

        'tipo' => 'digital',

    ],

    'instalacion-windows-drivers' => [

        'id' => 2,

        'nombre' => 'Windows + Drivers Installation',

        'descripcion' => 'Operating system ready for launch. Clean and secure setup.<br><br>Includes:<br>• Windows 10/11 installation<br>• All drivers installed<br>• System activation<br>• Initial setup<br>• Critical updates installed',

        'precio' => 49.00,

        'imagen' => 'assets/images/productos/instalacion-windows-drivers.png',

        'categoria' => 'tecnologia',

        'slug' => 'instalacion-windows-drivers',

        'tipo' => 'digital',

    ],

    'optimizacion-gamer' => [

        'id' => 3,

        'nombre' => 'Gamer Optimization (FPS, temperatures, storage)',

        'descripcion' => 'Your PC tuned to dominate the gaming galaxy.<br><br>Includes:<br>• FPS optimization in games<br>• Temperature control<br>• HDD/SSD optimization<br>• Graphics settings<br>• RAM optimization<br>• Safe overclock configuration',

        'precio' => 49.00,

        'imagen' => 'assets/images/productos/optimizacion-gamer-fps-temperaturas-disco.png',

        'categoria' => 'tecnologia',

        'slug' => 'optimizacion-gamer',

        'tipo' => 'digital',

    ],

    'activacion-juegos-giftcards' => [

        'id' => 4,

        'nombre' => 'Game & Gift Card Activation',

        'descripcion' => 'Digital activation service for official game and platform gift cards.<br><br>Available platforms:<br>• Steam<br>• PlayStation Network<br>• Xbox Live<br>• Riot Games<br>• Epic Games<br>• Nintendo eShop<br>• Origin/EA Play',

        'precio' => 3.00,

        'imagen' => 'assets/images/productos/activacion-juegos-giftcards.png',

        'categoria' => 'gaming',

        'slug' => 'activacion-juegos-giftcards',

        'tipo' => 'digital',

    ],

    'asesoria-pc-gamer' => [

        'id' => 5,

        'nombre' => 'PC Gamer Consulting (Custom Budget)',

        'descripcion' => 'Got $300 or $3000? We build the perfect rig.<br><br>Includes:<br>• Needs analysis<br>• Component selection<br>• Compatibility check<br>• Optimized shopping list<br>• Assembly guide<br>• Post-purchase support',

        'precio' => 19.00,

        'imagen' => 'assets/images/productos/asesoria-pc-gamer-presupuesto.png',

        'categoria' => 'gaming',

        'slug' => 'asesoria-pc-gamer',

        'tipo' => 'digital',

    ],


    'wallpaper-personalizado' => [

        'id' => 7,

        'nombre' => 'AI Custom Wallpaper',

        'descripcion' => 'Your background, your ship. Generated to spec.<br><br>Includes:<br>• Custom design<br>• Multiple resolutions<br>• KND galactic style<br>• Mobile version included<br>• High-quality files<br>• Free revisions',

        'precio' => 6.00,

        'imagen' => 'assets/images/productos/wallpaper-personalizado-knd.png',

        'categoria' => 'accesorios',

        'slug' => 'wallpaper-personalizado',

        'tipo' => 'digital',

    ],

    'avatar-personalizado' => [

        'id' => 8,

        'nombre' => 'Custom Gamer Avatar',

        'descripcion' => 'Build your digital identity with a galactic style.<br><br>Includes:<br>• Unique design<br>• Multiple formats<br>• KND galactic style<br>• Social media versions<br>• Editable files<br>• Included revisions',

        'precio' => 6.00,

        'imagen' => 'assets/images/productos/avatar-gamer-personalizado.png',

        'categoria' => 'accesorios',

        'slug' => 'avatar-personalizado',

        'tipo' => 'digital',

    ],

    'icon-pack-knd' => [

        'id' => 9,

        'nombre' => 'Icon Pack (Special Edition)',

        'descripcion' => 'Reinvent your desktop with a cosmic aesthetic.<br><br>Includes:<br>• Full icon pack<br>• KND galactic style<br>• Multiple sizes<br>• Installation instructions<br>• Customizable icons<br>• Technical support',

        'precio' => 15.00,

        'imagen' => 'assets/images/productos/icon-pack-edicion-especial.png',

        'categoria' => 'accesorios',

        'slug' => 'icon-pack-knd',

        'tipo' => 'digital',

    ],

    'instalacion-software' => [

        'id' => 10,

        'nombre' => 'Office, Adobe, OBS Installation',

        'descripcion' => 'Apps ready to use. Fully operational.<br><br>Available software:<br>• Microsoft Office<br>• Adobe Creative Suite<br>• OBS Studio<br>• Antivirus<br>• Browsers<br>• Developer tools',

        'precio' => 39.00,

        'imagen' => 'assets/images/productos/instalacion-programas-office-adobe-obs.png',

        'categoria' => 'software',

        'slug' => 'instalacion-software',

        'tipo' => 'digital',

    ],

    'pc-ready-pack' => [

        'id' => 11,

        'nombre' => 'PC Ready Pack (Software + Setup)',

        'descripcion' => 'Everything installed, optimized, and ready to game or work.<br><br>Includes:<br>• Windows installation<br>• All drivers<br>• Essential software<br>• Full optimization<br>• Security setup<br>• System backup',

        'precio' => 79.00,

        'imagen' => 'assets/images/productos/pc-ready-pack-software-configuracion.png',

        'categoria' => 'software',

        'slug' => 'pc-ready-pack',

        'tipo' => 'digital',

    ],

    'tutorial-knd' => [

        'id' => 12,

        'nombre' => 'Mini Tutorial (PDF or Express Video)',

        'descripcion' => 'Don’t know something? We explain it clearly and with style.<br><br>Available formats:<br>• Detailed PDF<br>• Video tutorial<br>• Step-by-step guide<br>• Screenshots with notes<br>• Practical examples<br>• Extra support',

        'precio' => 12.00,

        'imagen' => 'assets/images/productos/mini-tutorial-pdf-video-express.png',

        'categoria' => 'software',

        'slug' => 'tutorial-knd',

        'tipo' => 'digital',

    ],

    'compatibilidad-piezas' => [

        'id' => 13,

        'nombre' => 'Parts Compatibility Check (Do they work together?)',

        'descripcion' => 'Avoid mistakes before you buy. We confirm everything.<br><br>Verification includes:<br>• CPU and motherboard compatibility<br>• RAM compatibility<br>• GPU compatibility<br>• Power supply compatibility<br>• Case compatibility<br>• Upgrade recommendations',

        'precio' => 9.00,

        'imagen' => 'assets/images/productos/compatibilidad-piezas-pc.png',

        'categoria' => 'hardware',

        'slug' => 'compatibilidad-piezas',

        'tipo' => 'digital',

    ],

    'simulacion-build' => [

        'id' => 14,

        'nombre' => 'Build Simulation with Custom PDF',

        'descripcion' => 'Full list + reference image for your future rig.<br><br>Includes:<br>• Full component list<br>• Reference image<br>• Price analysis<br>• Suggested alternatives<br>• Assembly guide<br>• Performance estimate',

        'precio' => 29.00,

        'imagen' => 'assets/images/productos/simulacion-build-pdf-personalizado.png',

        'categoria' => 'hardware',

        'slug' => 'simulacion-build',

        'tipo' => 'digital',

    ],

    'analisis-pc' => [

        'id' => 15,

        'nombre' => 'PC Performance Analysis',

        'descripcion' => 'Running fine or lacking power? We analyze it with you.<br><br>Analysis includes:<br>• CPU performance<br>• GPU performance<br>• RAM analysis<br>• Storage speed<br>• System temperatures<br>• Upgrade recommendations',

        'precio' => 19.00,

        'imagen' => 'assets/images/productos/analisis-rendimiento-pc.png',

        'categoria' => 'hardware',

        'slug' => 'analisis-pc',

        'tipo' => 'digital',

    ],

    // ====== APPAREL PRODUCTS ======
    
    'hoodie-knd-style' => [
        'id' => 16,
        'nombre' => 'Hoodie KND Style',
        'descripcion' => 'Official KND hoodie with a unique galactic design.<br><br>Features:<br>• Premium fabric<br>• Exclusive KND design<br>• Available in 3 colors<br>• Sizes S/M/L/XL<br>• + Coordinated delivery',
        'precio' => 44.99,
        'imagen' => 'assets/images/Cloths/Hoodie Desing 001 MAGENTA.webp',
        'categoria' => 'apparel',
        'slug' => 'hoodie-knd-style',
        'tipo' => 'apparel',
        'variants' => [
            'magenta' => [
                'imagen' => 'assets/images/Cloths/Hoodie Desing 001 MAGENTA.webp',
                'sizes' => ['S', 'M', 'L', 'XL']
            ],
            'black' => [
                'imagen' => 'assets/images/Cloths/Hoodie Desing 001 NEGRA.webp',
                'sizes' => ['S', 'M', 'L', 'XL']
            ],
            'turquoise' => [
                'imagen' => 'assets/images/Cloths/Hoodie Desing 001 TURQUESA.webp',
                'sizes' => ['S', 'M', 'L', 'XL']
            ]
        ]
    ],

    'tshirt-knd-oversize' => [
        'id' => 17,
        'nombre' => 'T-Shirt KND Oversize',
        'descripcion' => 'Oversize T-shirt with KND galactic style.<br><br>Features:<br>• Comfortable oversize fit<br>• Exclusive KND design<br>• Available in 3 colors<br>• Sizes S/M/L/XL<br>• + Coordinated delivery',
        'precio' => 24.99,
        'imagen' => 'assets/images/Cloths/T-shirt Desing 001 MAGENTA.png',
        'categoria' => 'apparel',
        'slug' => 'tshirt-knd-oversize',
        'tipo' => 'apparel',
        'variants' => [
            'magenta' => [
                'imagen' => 'assets/images/Cloths/T-shirt Desing 001 MAGENTA.png',
                'sizes' => ['S', 'M', 'L', 'XL']
            ],
            'black' => [
                'imagen' => 'assets/images/Cloths/T-shirt Desing 001 NEGRA.png',
                'sizes' => ['S', 'M', 'L', 'XL']
            ],
            'turquoise' => [
                'imagen' => 'assets/images/Cloths/T-shirt Desing 001 TURQUESA.png',
                'sizes' => ['S', 'M', 'L', 'XL']
            ]
        ]
    ],

    'hoodie-knd-black-edition' => [
        'id' => 18,
        'nombre' => 'Hoodie KND Black Edition',
        'descripcion' => 'Special black edition with premium design.<br><br>Features:<br>• Premium fabric<br>• Exclusive Black Edition design<br>• Sizes S/M/L/XL<br>• + Coordinated delivery',
        'precio' => 49.99,
        'imagen' => 'assets/images/Cloths/Hoodie BLACK FRONT.png',
        'categoria' => 'apparel',
        'slug' => 'hoodie-knd-black-edition',
        'tipo' => 'apparel',
        'gallery' => [
            'front' => 'assets/images/Cloths/Hoodie BLACK FRONT.png',
            'back' => 'assets/images/Cloths/Hoodie BLACK BACK.png',
            'side' => 'assets/images/Cloths/Hoodie BLACK SIDE.png'
        ],
        'variants' => [
            'black' => [
                'imagen' => 'assets/images/Cloths/Hoodie BLACK FRONT.png',
                'sizes' => ['S', 'M', 'L', 'XL']
            ]
        ]
    ],

    'hoodie-anime-style' => [
        'id' => 19,
        'nombre' => 'Hoodie Anime Style (Limited Drop)',
        'descripcion' => 'Limited edition with a unique anime design.<br><br>Features:<br>• LIMITED edition<br>• Exclusive anime design<br>• Tri-Tone colorway<br>• Sizes S/M/L/XL<br>• + Coordinated delivery',
        'precio' => 59.99,
        'imagen' => 'assets/images/Cloths/Hoodie Anime PINK FRONT.png',
        'categoria' => 'apparel',
        'slug' => 'hoodie-anime-style',
        'tipo' => 'apparel',
        'limited' => true,
        'gallery' => [
            'front' => 'assets/images/Cloths/Hoodie Anime PINK FRONT.png',
            'back' => 'assets/images/Cloths/Hoodie Anime PINK BACK.png'
        ],
        'variants' => [
            'tri-tone' => [
                'imagen' => 'assets/images/Cloths/Hoodie Anime PINK FRONT.png',
                'sizes' => ['S', 'M', 'L', 'XL']
            ]
        ]
    ],

    'hoodie-dark-eyes-style' => [
        'id' => 20,
        'nombre' => 'Hoodie Dark Eyes Style (Limited Drop)',
        'descripcion' => 'Limited edition with a high-contrast Dark Eyes design.<br><br>Features:<br>• LIMITED edition<br>• Original Dark Eyes design<br>• Dark colorway with neon accents<br>• Sizes S/M/L/XL<br>• + Coordinated delivery',
        'precio' => 59.99,
        'imagen' => 'assets/images/Cloths/Hoodie Dark Eyes Front.png',
        'categoria' => 'apparel',
        'slug' => 'hoodie-dark-eyes-style',
        'tipo' => 'apparel',
        'limited' => true,
        'gallery' => [
            'front' => 'assets/images/Cloths/Hoodie Dark Eyes Front.png',
            'back' => 'assets/images/Cloths/Hoodie Dark Eyes Back.png'
        ],
        'variants' => [
            'tri-tone' => [
                'imagen' => 'assets/images/Cloths/Hoodie Dark Eyes Front.png',
                'sizes' => ['S', 'M', 'L', 'XL']
            ]
        ]
    ],

    // ====== CREATIVE CONSULTATION ======

    'creative-design-consultation' => [
        'id' => 24,
        'nombre' => 'Creative Design Consultation',
        'descripcion' => 'Structured creative guidance for branding, visual identity and digital aesthetics. Perfect for creators and digital entrepreneurs.<br><br>Includes:<br>• 30-minute consultation<br>• Strategic visual direction<br>• Practical improvement plan<br>• Follow-up summary',
        'precio' => 3.00,
        'imagen' => 'assets/images/productos/creative-design-consultation.png',
        'categoria' => 'accesorios',
        'slug' => 'creative-design-consultation',
        'tipo' => 'digital',
    ],

    // ====== CUSTOM SERVICES ======

    'custom-tshirt-design' => [
        'id' => 21,
        'nombre' => 'Custom T-Shirt Design (Service)',
        'descripcion' => 'Custom design service with made-to-order print production. All designs are original and follow intellectual property policies.<br><br>Includes:<br>• Unique custom design<br>• Professional mockup<br>• Editable files<br>• Basic revision included<br>• Digital delivery of the design<br>• WhatsApp coordination',
        'precio' => 24.99,
        'imagen' => 'assets/images/Cloths/T-shirt Desing Custom Francisco A #1.png',
        'categoria' => 'accesorios',
        'slug' => 'custom-tshirt-design',
        'tipo' => 'service'
    ],

    'custom-hoodie-design' => [
        'id' => 22,
        'nombre' => 'Custom Hoodie Design (Service)',
        'descripcion' => 'Custom design service with made-to-order print production. All designs are original and follow intellectual property policies.<br><br>Includes:<br>• Unique custom design<br>• Professional mockup<br>• Editable files<br>• Basic revision included<br>• Digital delivery of the design<br>• WhatsApp coordination',
        'precio' => 34.99,
        'imagen' => 'assets/images/Cloths/Hoodie Desing CUSTOM Francsico A.png',
        'categoria' => 'accesorios',
        'slug' => 'custom-hoodie-design',
        'tipo' => 'service',
        'gallery' => [
            'espanol' => 'assets/images/Cloths/Hoodie Desing CUSTOM Francsico A.png',
            'japones' => 'assets/images/Cloths/Hoodie Desing CUSTOM Francsico A JAPONESSE.png'
        ]
    ],

    'custom-full-outfit-concept' => [
        'id' => 23,
        'nombre' => 'Custom Full Outfit Concept (Service)',
        'descripcion' => 'Custom design service with made-to-order print production. All designs are original and follow intellectual property policies.<br><br>Includes:<br>• Full outfit concept<br>• Hoodie + T-shirt design<br>• Color variations<br>• Professional mockups<br>• Editable files<br>• Basic revision included<br>• Digital delivery<br>• WhatsApp coordination',
        'precio' => 69.99,
        'imagen' => 'assets/images/Cloths/T-shirt Desing Custom Francisco A #1.png',
        'categoria' => 'accesorios',
        'slug' => 'custom-full-outfit-concept',
        'tipo' => 'service',
        'gallery' => [
            'tshirt' => 'assets/images/Cloths/T-shirt Desing Custom Francisco A #1.png',
            'hoodie' => 'assets/images/Cloths/Hoodie Desing CUSTOM Francsico A.png'
        ]
    ],

];

