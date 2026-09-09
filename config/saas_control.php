<?php
return [
 'enabled'=>(bool)env('SAAS_CONTROL_ENABLED',false),
 'host'=>env('SAAS_CONTROL_HOST','sierra-control.internal'),
 'keys'=>env('SAAS_CONTROL_KEYS_FILE') ? require env('SAAS_CONTROL_KEYS_FILE') : [],
 'provision_enabled'=>(bool)env('SAAS_CONTROL_PROVISION_ENABLED',false),
 'reserved_slugs'=>array_values(array_filter(explode(',',env('SAAS_RESERVED_SLUGS','admin,auth,estoque,www,api,hml,belem,manaus,grafana,admin-manaus')))),
];
