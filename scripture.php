<?php

require_once('docx.php');
require_once('books.php');

function cleanUpScripture($scripture)
{
    $scripture = preg_replace('/：/', ':', $scripture);
    $scripture = preg_replace('/，/', ',', $scripture);
    $scripture = preg_replace('/,/', ', ', $scripture);
    $scripture = preg_replace('/-/', '-', $scripture);
    $scripture = preg_replace('/: +/', ':', $scripture);
    $scripture = preg_replace('/；/', '; ', $scripture);
    $scripture = preg_replace('/([^A-Za-z0-9: ,;_【】\n-])([A-Za-z0-9])/', '$1 $2', $scripture);
    $scripture = preg_replace_callback('/(【|^) *([^【\x00-\x7f]+) *(\d[\d:】-])/', function ($matches) {
        global $chineseToEnglish;
        echo "HERE3:\n";
        print_r($matches);
        if ($matches && $chineseToEnglish[$matches[2]])
            return $matches[1] . $matches[2] . ' ' . $chineseToEnglish[$matches[2]] . ' ' . $matches[3];
        else
            return $matches[0];
    }, $scripture);
    $scripture = preg_replace('/【 +/', '【', $scripture);
    $scripture = preg_replace('/ +】/', '】', $scripture);
    $scripture = trim($scripture);
    echo "SPECIAL: $scripture\n";
    return $scripture;
}

try {
    $docText = RD_Text_Extraction::convert_to_text('../sermonspeaker/friday/2017-05-27_Genesis-Lesson-3_BCCC.pptx');
    preg_match_all('/【(.*?)】/', $docText, $matches, PREG_PATTERN_ORDER);
    $scripture = implode("\n", $matches[0]);
//    echo "HERE: $scripture\n";
    $scripture = cleanUpScripture($scripture);
//    echo "SPECIAL: $scripture\n";

} catch(Exception $e) {
    echo $e->getMessage();
}
