<?php

declare(strict_types=1);

return [
    'modal' => [
        'heading_locked' => 'Dit record wordt momenteel bewerkt',
        'heading_taken_over' => 'Uw vergrendeling is overgenomen',
        'heading_stale' => 'Het record is gewijzigd',
        'locked' => ':user bewerkt dit record sinds :since. Wacht tot de bewerking klaar is of open het alleen-lezen.',
        'locked_unknown' => 'Een andere gebruiker bewerkt dit record. Wacht tot de bewerking klaar is of open het alleen-lezen.',
        'taken_over' => ':user heeft dit record overgenomen. Uw niet-opgeslagen wijzigingen kunnen hier niet meer worden opgeslagen.',
        'taken_over_unknown' => 'Een andere gebruiker heeft dit record overgenomen. Uw niet-opgeslagen wijzigingen kunnen hier niet meer worden opgeslagen.',
        'stale' => 'Iemand heeft dit record gewijzigd en opgeslagen terwijl uw vergrendeling was verlopen. Laad het opnieuw om met de actuele gegevens verder te gaan.',
        'back' => 'Terug naar lijst',
        'view' => 'Alleen-lezen bekijken',
        'unlock' => 'Ontgrendelen en bewerken',
        'reload' => 'Record opnieuw laden',
    ],
    'notifications' => [
        'taken_over_title' => 'Uw vergrendeling is overgenomen',
        'taken_over_body' => 'Een andere gebruiker heeft dit record overgenomen. Uw niet-opgeslagen wijzigingen kunnen niet meer worden opgeslagen.',
        'save_refused_title' => 'Niet opgeslagen',
        'save_refused_body' => 'U heeft de vergrendeling van dit record niet meer, dus uw wijzigingen zijn niet opgeslagen.',
        'unlock_denied' => 'U mag records niet ontgrendelen.',
    ],
    'actions' => [
        'release' => 'Vergrendeling opheffen',
        'release_heading' => 'Deze vergrendeling opheffen?',
        'release_description' => 'Wie het record bewerkt verliest de vergrendeling en kan de wijzigingen niet opslaan.',
        'released' => 'Vergrendeling opgeheven',
        'release_selected' => 'Vergrendelingen opheffen',
        'release_selected_heading' => 'Geselecteerde vergrendelingen opheffen?',
        'released_count' => 'Opgeheven vergrendelingen: :count',
        'release_all' => 'Alle vergrendelingen opheffen',
        'release_all_description' => 'Elke vergrendeling wordt verwijderd en wie records bewerkt verliest die.',
        'released_all' => 'Alle vergrendelingen opgeheven',
    ],
    'resource' => [
        'navigation' => 'Recordvergrendelingen',
        'singular' => 'recordvergrendeling',
        'plural' => 'Recordvergrendelingen',
    ],
    'table' => [
        'type' => 'Type',
        'record' => 'Record',
        'holder' => 'Vergrendeld door',
        'since' => 'Vergrendeld sinds',
        'expires' => 'Verloopt',
        'status' => 'Status',
    ],
    'status' => [
        'active' => 'Actief',
        'expired' => 'Verlopen',
    ],
    'column' => [
        'mine' => 'U bewerkt dit record',
        'other' => ':user bewerkt dit record (sinds :since)',
        'other_unknown' => 'Vergrendeld door een andere gebruiker',
    ],
];
