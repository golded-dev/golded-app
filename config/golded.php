<?php

declare(strict_types=1);

return [
    'version' => 'GoldED 7',
    'username' => 'Demo User',
    'address' => '2:999/1',
    'charset_import' => 'CP850',
    'tearline' => '@longpid @version',
    'origins' => [
        'GoldED 7 public demo',
    ],
    'taglines' => [
        'Synthetic mail. Real nostalgia.',
    ],
    'arealistsort' => 'FYTUE',
    'areasep' => [
        ['label' => 'Demo areas', 'area_type' => 'Echo'],
    ],
    'areas' => [
        'M:\\msg\\DEMO' => [
            'echoid' => 'GOLDED.DEMO',
            'description' => 'Synthetic GoldED demo area',
            'group_id' => 'D',
            'area_type' => 'Echo',
            'format' => 'opus',
        ],
    ],
];
