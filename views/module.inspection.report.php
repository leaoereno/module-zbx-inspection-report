<?php

$html_page = (new CHtmlPage())->setTitle(_('Inspection report'));

$form_list = (new CFormList())->addRow(
    (new CDiv(_('This form allows you to view the inspection report of Zabbix components through a webpage or Excel format.')))
);

// Selection error feedback (the controller redirects here with ?sel=N when a
// host was selected in the wrong role).
$sel_messages = [
    0 => _('The server selection field cannot be empty. Please reselect.'),
    1 => _('The server has been mistakenly selected as a proxy. Please reselect.'),
    2 => _('The server has been mistakenly selected as a database. Please reselect.'),
    3 => _('The proxy has been mistakenly selected as a server. Please reselect.'),
    4 => _('The proxy has been mistakenly selected as a database. Please reselect.'),
    5 => _('The database has been mistakenly selected as a server. Please reselect.'),
    6 => _('The database has been mistakenly selected as a proxy. Please reselect.')
];

if (array_key_exists($data['sel'], $sel_messages)) {
    $form_list->addRow(
        (new CLabel($sel_messages[$data['sel']]))->addClass(ZBX_STYLE_RED)
    );
}

$multiselects = [
    ['zabbix_server_ids', _('Zabbix server'), true],
    ['zabbix_proxy_ids', _('Zabbix proxy'), false],
    ['zabbix_database_ids', _('Zabbix database'), false]
];

foreach ($multiselects as [$field, $label, $required]) {
    $label_obj = new CLabel($label, $field . '_ms');
    if ($required) {
        $label_obj->setAsteriskMark();
    }

    $form_list->addRow(
        $label_obj,
        (new CMultiSelect([
            'name' => $field . '[]',
            'object_name' => 'hosts',
            'data' => [],
            'multiple' => true,
            'popup' => [
                'parameters' => [
                    'srctbl' => 'hosts',
                    'srcfld1' => 'hostid',
                    'srcfld2' => 'host',
                    'dstfrm' => 'inspectionReportForm',
                    'dstfld1' => $field . '_'
                ]
            ]
        ]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
    );
}

$form_list->addRow(
    (new CLabel(_('Inspection cycle'), 'inspection_cycle'))->setAsteriskMark(),
    (new CRadioButtonList('inspection_cycle', (int) $data['inspection_cycle']))
        ->addValue(_('First quarter'), 0)
        ->addValue(_('Second quarter'), 1)
        ->addValue(_('Third quarter'), 2)
        ->addValue(_('Fourth quarter'), 3)
        ->setModern(true)
);

$form = (new CForm())
    ->setId('inspection-report-form')
    ->setName('inspectionReportForm')
    ->setAction((new CUrl('zabbix.php'))
        ->setArgument('action', 'inspection.detail')
        ->getUrl()
    )
    ->addItem(
        (new CTabView())
            ->addTab('inspection.report', _('Inspection report'), $form_list)
            ->setFooter(makeFormFooter(
                new CSubmit('generate', _('Generate')),
                [(new CSimpleButton(_('Reset')))->onClick("document.location = " . json_encode((new CUrl('zabbix.php'))->setArgument('action', 'inspection.report')->getUrl()))]
            ))
    );

$html_page->addItem($form)->show();
