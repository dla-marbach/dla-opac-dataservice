<?php
/**
 * @category    subcollection
 * @package     Subcollection
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Subcollection
    |--------------------------------------------------------------------------
    |
    | This value gives, the maximum number of records a request can return
    |
    */

    // Kracauer
    '1' =>
        [
            'info' => 'Personalbibliographie Siegfried Kracauer',
            'query' => 'noteBibliography_mv:Kracauer-Bibliographie',
            'url' => 'https://www.dla-marbach.de/bibliothek/bibliografien/siegfried-kracauer-personalbibliografie'
        ],
    // Döblin
    '2' =>
        [
            'info' => 'Personalbibliographie Alfred Döblin',
            'query' => 'noteBibliography_mv:Döblin-Bibliographie',
            'url' => 'https://www.dla-marbach.de/bibliothek/bibliografien/alfred-doeblin-personalbibliografie'
        ],
    '3' =>
        [
            'info' => 'Werknormsätze Siegfried Kracauer',
            'query' => 'category:Werktitel AND personBy_id_mv:PE00003459',
            'url' => 'https://www.dla-marbach.de/bibliothek/projekte/werktitel-als-wissensraum/'
        ],
    '4' =>
        [
            'info' => 'Werknormsätze Alfred Döblin',
            'query' => 'category:Werktitel AND personBy_id_mv:PE00006675',
            'url' => 'https://www.dla-marbach.de/bibliothek/projekte/werktitel-als-wissensraum/'
        ],
    '5' =>
        [
            'info' => 'Autorenbibliothek Siegfried Kracauer - Titeldaten',
            'query' => 'item_holding_id_mv:BF00019677',
            'url' => 'https://www.dla-marbach.de/bibliothek/spezialsammlungen/bestandsliste/bibliothek-siegfried-kracauer'
        ],
    '6' =>
        [
            'info' => 'Autorenbibliothek Alfred Döblin - Titeldaten',
            'query' => 'item_holding_id_mv:BF00019097',
            'url' => 'https://www.dla-marbach.de/bibliothek/spezialsammlungen/bestandsliste/bibliothek-alfred-doeblin'
        ],
    '7' =>
        [
            'info' => 'Autorenbibliothek Siegfried Kracauer - Exemplare und Provenienzmerkmale',
            'query' => 'holding_id_mv:BF00019677',
            'url' => 'https://www.dla-marbach.de/bibliothek/spezialsammlungen/bestandsliste/bibliothek-siegfried-kracauer'
        ],
    '8' =>
        [
            'info' => 'Autorenbibliothek Alfred Döblin - Exemplare und Provenienzmerkmale',
            'query' => 'holding_id_mv:BF00019097',
            'url' => 'https://www.dla-marbach.de/bibliothek/spezialsammlungen/bestandsliste/bibliothek-alfred-doeblin'
        ]
];