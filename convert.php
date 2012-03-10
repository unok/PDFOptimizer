<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);
require_once('lib/config.php');

if (!is_dir(Config::WATCH_DIR_PATH))
{
    echo("ERROR: Not a directory WATCH_DIR_PATH: " . Config::WATCH_DIR_PATH . "\n");
    exit(1);
}
if (!is_dir(Config::TEMP_DIR_PATH))
{
    echo("ERROR: Not a directory TEMP_DIR_PATH: " . Config::TEMP_DIR_PATH . "\n");
    exit(1);
}
if (!is_dir(Config::OUTPUT_DIR_PATH))
{
    echo("ERROR: Not a directory OUTPUT_DIR_PATH: " . Config::OUTPUT_DIR_PATH . "\n");
    exit(1);
}

$wdp = opendir(Config::WATCH_DIR_PATH);
if ($wdp === false)
{
    echo("ERROR: Cannot open directory: " . Config::WATCH_DIR_PATH . "\n");
    exit(1);
}

$file_list =
    array_merge(
        glob(Config::WATCH_DIR_PATH . '*' . DS . '*.pdf'),
        glob(Config::WATCH_DIR_PATH . '*' . DS . '*' . DS . '*.pdf'));
var_dump($file_list);
foreach ($file_list as $file)
{
    if (file_exists($file) && is_file($file))
    {
        convert($file);
    }
}

function convert($file)
{
    echo $file . "\n";
    $parts = preg_split('/' . preg_quote(DS, DS) . '/', $file);
    $pdf_file_name = array_pop($parts);
    $include = array();
    $exclude = array();
    $convert_cmd = '';
    if (preg_match('/^(exclude(_\d+)*|(\d+)(_\d+)*)$/', $parts[count($parts) - 1]))
    {
        if (preg_match('/^exclude((_\d+)+)$/', $parts[count($parts) - 1], $m))
        {
            $exclude = preg_split('/_/', $m[1]);
        }
        else
        {
            $include = preg_split('/_/', $parts[count($parts) - 1]);
        }
        $convert_cmd = $parts[count($parts) - 2];
    }
    else
    {
        $convert_cmd = $parts[count($parts) - 1];
    }
    $uid = date('Ymd') . uniqid();
    $TEMP_DIR = Config::TEMP_DIR_PATH . $uid . DS;
    mkdir($TEMP_DIR) or die;
    rename($file, $TEMP_DIR . $pdf_file_name);
    $CMD = sprintf(Config::PDF_EXPORT_CMD, $TEMP_DIR, $TEMP_DIR . $pdf_file_name);
    echo $CMD . "\n";
    $convert_cmd = preg_replace('/__FILE__/', '"%1$s"', $convert_cmd);
    echo $convert_cmd . "\n";
    system($CMD);
    $file_list = glob($TEMP_DIR . '*.jpeg');
    if (count($include))
    {
        foreach ($include as $page)
        {
            $page_file = $TEMP_DIR . sprintf(Config::IMAGE_FILE_FORMAT, $page);
            $CONVERT_CMD = sprintf($convert_cmd, addslashes($page_file));
            echo $CONVERT_CMD . "\n";
            system($CONVERT_CMD);
        }
    }
    else
    {
        foreach ($file_list as $page_file)
        {
            preg_match('/.*?(\d+).jpeg$/', $page_file, $m);
            $page = $m[1] + 0;
            if (array_search($page, $exclude) !== false)
            {
                continue;
            }
            $CONVERT_CMD = sprintf($convert_cmd, addslashes($page_file));
            echo $CONVERT_CMD . "\n";
            system($CONVERT_CMD);
        }
    }
    $CREATE_PDF_CMD = sprintf(Config::PDF_CREATE_CMD, addslashes($TEMP_DIR),
        addslashes(Config::OUTPUT_DIR_PATH . $pdf_file_name));
    echo $CREATE_PDF_CMD . "\n";
    system($CREATE_PDF_CMD);
    reset($file_list);
    foreach ($file_list as $page_file)
    {
       unlink($page_file);
    }
    unlink($TEMP_DIR . $pdf_file_name);
    rmdir($TEMP_DIR);
}