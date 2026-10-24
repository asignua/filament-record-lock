<?php

declare(strict_types=1);

return [
    'modal' => [
        'heading_locked' => 'Questo record è in fase di modifica',
        'heading_taken_over' => 'Il tuo blocco è stato rilevato',
        'heading_stale' => 'Il record è cambiato',
        'locked' => ':user sta modificando questo record dalle :since. Attendi che finisca oppure aprilo in sola lettura.',
        'locked_unknown' => 'Un altro utente sta modificando questo record. Attendi che finisca oppure aprilo in sola lettura.',
        'taken_over' => ':user ha rilevato questo record. Le tue modifiche non salvate non possono più essere salvate qui.',
        'taken_over_unknown' => 'Un altro utente ha rilevato questo record. Le tue modifiche non salvate non possono più essere salvate qui.',
        'stale' => 'Qualcuno ha modificato e salvato questo record mentre il tuo blocco era scaduto. Ricaricalo per continuare con i dati attuali.',
        'back' => 'Torna all\'elenco',
        'view' => 'Visualizza in sola lettura',
        'unlock' => 'Sblocca e modifica',
        'reload' => 'Ricarica il record',
    ],
    'notifications' => [
        'taken_over_title' => 'Il tuo blocco è stato rilevato',
        'taken_over_body' => 'Un altro utente ha rilevato questo record. Le tue modifiche non salvate non possono più essere salvate.',
        'save_refused_title' => 'Non salvato',
        'save_refused_body' => 'Non detieni più il blocco di questo record, quindi le modifiche non sono state salvate.',
        'unlock_denied' => 'Non hai il permesso di sbloccare i record.',
    ],
    'actions' => [
        'release' => 'Rilascia il blocco',
        'release_heading' => 'Rilasciare questo blocco?',
        'release_description' => 'Chi sta modificando il record perde il blocco e non potrà salvare le modifiche.',
        'released' => 'Blocco rilasciato',
        'release_selected' => 'Rilascia i blocchi',
        'release_selected_heading' => 'Rilasciare i blocchi selezionati?',
        'released_count' => 'Blocchi rilasciati: :count',
        'release_all' => 'Rilascia tutti i blocchi',
        'release_all_description' => 'Ogni blocco viene eliminato e chi sta modificando dei record lo perde.',
        'released_all' => 'Tutti i blocchi rilasciati',
    ],
    'resource' => [
        'navigation' => 'Blocchi dei record',
        'singular' => 'blocco del record',
        'plural' => 'Blocchi dei record',
    ],
    'table' => [
        'type' => 'Tipo',
        'record' => 'Record',
        'holder' => 'Bloccato da',
        'since' => 'Bloccato dalle',
        'expires' => 'Scade',
        'status' => 'Stato',
    ],
    'status' => [
        'active' => 'Attivo',
        'expired' => 'Scaduto',
    ],
    'column' => [
        'mine' => 'Stai modificando questo record',
        'other' => ':user sta modificando questo record (dalle :since)',
        'other_unknown' => 'Bloccato da un altro utente',
    ],
];
