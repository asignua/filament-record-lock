<?php

declare(strict_types=1);

return [
    'modal' => [
        'heading_locked' => 'Ten rekord jest teraz edytowany',
        'heading_taken_over' => 'Twoja blokada została przejęta',
        'heading_stale' => 'Rekord został zmieniony',
        'locked' => ':user edytuje ten rekord od :since. Poczekaj, aż skończy, lub otwórz go tylko do odczytu.',
        'locked_unknown' => 'Inny użytkownik edytuje ten rekord. Poczekaj, aż skończy, lub otwórz go tylko do odczytu.',
        'taken_over' => ':user przejął ten rekord. Twoich niezapisanych zmian nie można już tutaj zapisać.',
        'taken_over_unknown' => 'Inny użytkownik przejął ten rekord. Twoich niezapisanych zmian nie można już tutaj zapisać.',
        'stale' => 'Ktoś zmienił i zapisał ten rekord, gdy Twoja blokada wygasła. Załaduj go ponownie, aby pracować na aktualnych danych.',
        'back' => 'Wróć do listy',
        'view' => 'Tylko podgląd',
        'unlock' => 'Odblokuj i edytuj',
        'reload' => 'Załaduj rekord ponownie',
    ],
    'notifications' => [
        'taken_over_title' => 'Twoja blokada została przejęta',
        'taken_over_body' => 'Inny użytkownik przejął ten rekord. Twoich niezapisanych zmian nie można już zapisać.',
        'save_refused_title' => 'Nie zapisano',
        'save_refused_body' => 'Nie masz już blokady tego rekordu, więc zmiany nie zostały zapisane.',
        'unlock_denied' => 'Nie masz uprawnień do odblokowywania rekordów.',
    ],
    'actions' => [
        'release' => 'Zwolnij blokadę',
        'release_heading' => 'Zwolnić tę blokadę?',
        'release_description' => 'Osoba edytująca rekord straci blokadę i nie zapisze swoich zmian.',
        'released' => 'Blokada zwolniona',
        'release_selected' => 'Zwolnij blokady',
        'release_selected_heading' => 'Zwolnić wybrane blokady?',
        'released_count' => 'Zwolnionych blokad: :count',
        'release_all' => 'Zwolnij wszystkie blokady',
        'release_all_description' => 'Każda blokada zostanie usunięta, a wszyscy edytujący rekordy ją stracą.',
        'released_all' => 'Wszystkie blokady zwolnione',
    ],
    'resource' => [
        'navigation' => 'Blokady rekordów',
        'singular' => 'blokada rekordu',
        'plural' => 'Blokady rekordów',
    ],
    'table' => [
        'type' => 'Typ',
        'record' => 'Rekord',
        'holder' => 'Zablokowane przez',
        'since' => 'Zablokowane od',
        'expires' => 'Wygasa',
        'status' => 'Status',
    ],
    'status' => [
        'active' => 'Aktywna',
        'expired' => 'Wygasła',
    ],
    'column' => [
        'mine' => 'Edytujesz ten rekord',
        'other' => ':user edytuje ten rekord (od :since)',
        'other_unknown' => 'Zablokowane przez innego użytkownika',
    ],
];
