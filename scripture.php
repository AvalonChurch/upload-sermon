<?php

require_once('docx.php');

try {
    $docText = RD_Text_Extraction::convert_to_text('../sermonspeaker/sermons/2016-11-06_Single-Minded_BCCC.pptx');
    preg_match_all('/【([^】]+)】/', $docText, $matches, PREG_PATTERN_ORDER);
    $scriptures = implode("\n", array_slice($matches[1], 1));
    print_r($scriptures);
} catch(Exception $e) {
    echo $e->getMessage();
}
