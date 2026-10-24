<?php

declare(strict_types=1);

return [
    'modal' => [
        'heading_locked' => 'Dieser Datensatz wird gerade bearbeitet',
        'heading_taken_over' => 'Ihre Sperre wurde übernommen',
        'heading_stale' => 'Der Datensatz hat sich geändert',
        'locked' => ':user bearbeitet diesen Datensatz seit :since. Warten Sie, bis die Bearbeitung beendet ist, oder öffnen Sie ihn schreibgeschützt.',
        'locked_unknown' => 'Ein anderer Benutzer bearbeitet diesen Datensatz. Warten Sie, bis die Bearbeitung beendet ist, oder öffnen Sie ihn schreibgeschützt.',
        'taken_over' => ':user hat diesen Datensatz übernommen. Ihre ungespeicherten Änderungen können hier nicht mehr gespeichert werden.',
        'taken_over_unknown' => 'Ein anderer Benutzer hat diesen Datensatz übernommen. Ihre ungespeicherten Änderungen können hier nicht mehr gespeichert werden.',
        'stale' => 'Jemand hat diesen Datensatz geändert und gespeichert, während Ihre Sperre abgelaufen war. Laden Sie ihn neu, um mit den aktuellen Daten weiterzuarbeiten.',
        'back' => 'Zurück zur Liste',
        'view' => 'Nur ansehen',
        'unlock' => 'Entsperren und bearbeiten',
        'reload' => 'Datensatz neu laden',
    ],
    'notifications' => [
        'taken_over_title' => 'Ihre Sperre wurde übernommen',
        'taken_over_body' => 'Ein anderer Benutzer hat diesen Datensatz übernommen. Ihre ungespeicherten Änderungen können nicht mehr gespeichert werden.',
        'save_refused_title' => 'Nicht gespeichert',
        'save_refused_body' => 'Sie halten die Sperre dieses Datensatzes nicht mehr, daher wurden Ihre Änderungen nicht gespeichert.',
        'unlock_denied' => 'Sie dürfen Datensätze nicht entsperren.',
    ],
    'actions' => [
        'release' => 'Sperre aufheben',
        'release_heading' => 'Diese Sperre aufheben?',
        'release_description' => 'Wer den Datensatz bearbeitet, verliert die Sperre und kann seine Änderungen nicht speichern.',
        'released' => 'Sperre aufgehoben',
        'release_selected' => 'Sperren aufheben',
        'release_selected_heading' => 'Ausgewählte Sperren aufheben?',
        'released_count' => 'Aufgehobene Sperren: :count',
        'release_all' => 'Alle Sperren aufheben',
        'release_all_description' => 'Jede Sperre wird gelöscht; alle, die gerade Datensätze bearbeiten, verlieren sie.',
        'released_all' => 'Alle Sperren aufgehoben',
    ],
    'resource' => [
        'navigation' => 'Datensatzsperren',
        'singular' => 'Datensatzsperre',
        'plural' => 'Datensatzsperren',
    ],
    'table' => [
        'type' => 'Typ',
        'record' => 'Datensatz',
        'holder' => 'Gesperrt von',
        'since' => 'Gesperrt seit',
        'expires' => 'Läuft ab',
        'status' => 'Status',
    ],
    'status' => [
        'active' => 'Aktiv',
        'expired' => 'Abgelaufen',
    ],
    'column' => [
        'mine' => 'Sie bearbeiten diesen Datensatz',
        'other' => ':user bearbeitet diesen Datensatz (seit :since)',
        'other_unknown' => 'Von einem anderen Benutzer gesperrt',
    ],
];
