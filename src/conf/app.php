<?php
$namespace = 'demo\\ui';
$app_class = '\\Ice\\Frame\\App';
$base_uri = '';
$default_controller = 'index';
$default_action = 'index';

$debug = TRUE;

$root_path = __DIR__ . '/..';
$var_path  = $root_path . '/../var';
$run_path  = $var_path . '/run';
$log_path  = $var_path . '/logs';

$frame = array(
    'server_env_class' => '\\Ice\\Frame\\Web\\ServerEnv',
    'client_env_class' => '\\Ice\\Frame\\Web\\ClientEnv',
    'request_class'    => '\\Ice\\Frame\\Web\\Request',
    'response_class'   => '\\Ice\\Frame\\Web\\Response',
);

$log = array(
    'common' => array( // common日志是固定的, 框架层会直接继承使用此logger
        'log_fmt' => array(),
        'log_fmt_wf' => array(),
        'log_file' => 'common.log',
        'log_path' => $var_path . '/logs',
        'split'    => array(
            'type' => 'file',
            'fmt'  => 'Ymd',
        ),
    ),
);

$temp_engine = array(
    'engines' => array(
        'json' => array(
            'adapter' => '\\Ice\\Frame\\Web\\TempEngine\\Json',
            'adapter_config' => array(
                'headers' => array('Content-Type: text/json;CHARSET=UTF-8'),
                'json_encode_options' => JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                'error_tpl' => '',
            ),
        ),
        'smarty-default' => array(
            'adapter' => '\\Ice\\Frame\\Web\\TempEngine\\Smarty',
            'adapter_config'  => array(
                'headers'   => array('Content-Type: text/html;CHARSET=UTF-8'),
                'error_tpl' => '_common/error',
                'ext_name'  => '.tpl',
            ),
            'temp_engine_config' => array(
                'cache_lifetime'        => 30 * 24 * 3600,
                'caching'               => false,
                'cache_dir'             => '',
                'use_sub_dirs'          => TRUE,
                'template_dir'          => $root_path . '/smarty-tpl',
                'plugins_dir'           => array(),
                'compile_dir'           => $run_path . '/smarty-compiled/' ,
                'default_modifiers'     => array('escape:"html"'),
                'left_delimiter'        => '{%',
                'right_delimiter'       => '%}',
            ),
        ),
    ),
    'routes' => array(
        '*' => 'json',
    ),
);
