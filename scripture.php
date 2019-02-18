<?php

require_once('docx.php');

try {
    $docText = RD_Text_Extraction::convert_to_text('../sermonspeaker/sermons/2016-11-06_Single-Minded_BCCC.pptx');
    print_r($docText);
    preg_match_all('/【(.*?)】/', $docText, $matches, PREG_PATTERN_ORDER);
    $scriptures = implode("\n", $matches[0]);
    print_r($scriptures);
    print_r($matches);
} catch(Exception $e) {
    echo $e->getMessage();
}
