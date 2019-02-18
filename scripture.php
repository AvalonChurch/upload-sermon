<?php

require_once('docx.php');

try {
    $docText = RD_Text_Extraction::convert_to_text('../sermonspeaker/friday/2017-05-27_Genesis-Lesson-3_BCCC.pptx');
    print_r($docText);
    preg_match_all('/【(.*?)】/', $docText, $matches, PREG_PATTERN_ORDER);
    $scriptures = implode("\n", $matches[0]);
    print_r($scriptures);
    print_r($matches);
    $scripture = implode("\n", $matches[0]);
    echo "HERE: $scripture\n";
    $scripture = preg_replace_callback_array('/(【*)([^0-9abc ,;-]+)(.*)/', function ($matches) {
        echo "HERE2:\n";
        print_r($matches);
        if($matches)
            return $matches[1].$matches[2].' >'.$matches[2].'< '.$matches[3];
        else
            return $matches[0];
    }, $scripture);
    echo "SPECIAL: $scripture\n";

} catch(Exception $e) {
    echo $e->getMessage();
}
