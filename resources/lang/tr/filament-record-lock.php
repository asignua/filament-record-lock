<?php

declare(strict_types=1);

return [
    'modal' => [
        'heading_locked' => 'Bu kayıt şu anda düzenleniyor',
        'heading_taken_over' => 'Kilidiniz devralındı',
        'heading_stale' => 'Kayıt değişti',
        'locked' => ':user bu kaydı :since tarihinden beri düzenliyor. İşini bitirmesini bekleyin veya kaydı salt okunur açın.',
        'locked_unknown' => 'Başka bir kullanıcı bu kaydı düzenliyor. İşini bitirmesini bekleyin veya kaydı salt okunur açın.',
        'taken_over' => ':user bu kaydı devraldı. Kaydedilmemiş değişiklikleriniz artık burada kaydedilemez.',
        'taken_over_unknown' => 'Başka bir kullanıcı bu kaydı devraldı. Kaydedilmemiş değişiklikleriniz artık burada kaydedilemez.',
        'stale' => 'Kilidiniz süresi dolmuşken biri bu kaydı değiştirip kaydetti. Güncel verilerle devam etmek için kaydı yeniden yükleyin.',
        'back' => 'Listeye dön',
        'view' => 'Salt okunur görüntüle',
        'unlock' => 'Kilidi aç ve düzenle',
        'reload' => 'Kaydı yeniden yükle',
    ],
    'notifications' => [
        'taken_over_title' => 'Kilidiniz devralındı',
        'taken_over_body' => 'Başka bir kullanıcı bu kaydı devraldı. Kaydedilmemiş değişiklikleriniz artık kaydedilemez.',
        'save_refused_title' => 'Kaydedilmedi',
        'save_refused_body' => 'Bu kaydın kilidine artık sahip değilsiniz, bu yüzden değişiklikleriniz kaydedilmedi.',
        'unlock_denied' => 'Kayıtların kilidini açma yetkiniz yok.',
    ],
    'actions' => [
        'release' => 'Kilidi serbest bırak',
        'release_heading' => 'Bu kilit serbest bırakılsın mı?',
        'release_description' => 'Kaydı düzenleyen kişi kilidi kaybeder ve değişikliklerini kaydedemez.',
        'released' => 'Kilit serbest bırakıldı',
        'release_selected' => 'Kilitleri serbest bırak',
        'release_selected_heading' => 'Seçilen kilitler serbest bırakılsın mı?',
        'released_count' => 'Serbest bırakılan kilit sayısı: :count',
        'release_all' => 'Tüm kilitleri serbest bırak',
        'release_all_description' => 'Her kilit silinir ve kayıt düzenleyen herkes kilidini kaybeder.',
        'released_all' => 'Tüm kilitler serbest bırakıldı',
    ],
    'resource' => [
        'navigation' => 'Kayıt kilitleri',
        'singular' => 'kayıt kilidi',
        'plural' => 'Kayıt kilitleri',
    ],
    'table' => [
        'type' => 'Tür',
        'record' => 'Kayıt',
        'holder' => 'Kilitleyen',
        'since' => 'Kilitlenme zamanı',
        'expires' => 'Sona erer',
        'status' => 'Durum',
    ],
    'status' => [
        'active' => 'Etkin',
        'expired' => 'Süresi doldu',
    ],
    'column' => [
        'mine' => 'Bu kaydı düzenliyorsunuz',
        'other' => ':user bu kaydı düzenliyor (:since tarihinden beri)',
        'other_unknown' => 'Başka bir kullanıcı tarafından kilitlendi',
    ],
];
