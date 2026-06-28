var CA_DT_DOM = '<"d-flex align-items-center justify-content-between mb-2"<"d-flex gap-2"B>f>rtip';

var CA_DT_BUTTONS = [{
    extend: 'collection',
    text: 'Exporter <i class="fas fa-caret-down ms-1" aria-hidden="true"></i>',
    className: 'btn btn-dt',
    buttons: [
        { extend: 'copy',  text: '<i class="fas fa-copy me-2" aria-hidden="true"></i>Copier' },
        { extend: 'excel', text: '<i class="fas fa-file-excel me-2" aria-hidden="true"></i>Excel' },
        { extend: 'print', text: '<i class="fas fa-print me-2" aria-hidden="true"></i>Imprimer' },
        { extend: 'pdf',   text: '<i class="fas fa-file-pdf me-2" aria-hidden="true"></i>PDF' }
    ]
}];

var CA_DT_COLVIS = {
    extend: 'colvis',
    text: '<i class="fas fa-columns me-1" aria-hidden="true"></i>Colonnes',
    className: 'btn btn-dt'
};

var CA_DT_LANGUAGE = {
    info: '_TOTAL_ entrées',
    infoFiltered: '(filtrées sur _MAX_)',
    search: '',
    searchPlaceholder: 'Filtrer…'
};
