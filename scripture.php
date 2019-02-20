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
        if ($matches && $chineseToEnglish[$matches[2]])
            return $matches[1] . $matches[2] . ' ' . $chineseToEnglish[$matches[2]] . ' ' . $matches[3];
        else
            return $matches[0];
    }, $scripture);
    $scripture = preg_replace('/【 +/', '【', $scripture);
    $scripture = preg_replace('/ +】/', '】', $scripture);
    $scripture = trim($scripture);
    return $scripture;
}

try {
    $docxText = RD_Text_Extraction::convert_to_text('../sermonspeaker/friday/2017-05-27_Genesis-Lesson-3_BCCC.pptx');
    preg_match_all('/【(.+?\d.*?)】/', $docxText, $matches, PREG_PATTERN_ORDER);
    $scripture = implode("\n", $matches[0]);
    $verses = array_values(array_unique($matches[1]));
    $verses = array_map(function($value) {
        return cleanUpScripture($value);
    }, $verses);
    print("VERSES:\n");
    print_r($verses);
    $unique_verses = array();
    $i = 0;
    while($i < count($verses)) {
        preg_match('/^(.*) (\d+):(\d+)$/', cleanUpScripture($verses[$i]), $ms);
        if($ms) {
            $mf = null;
            $m = $ms;
            do {
                $i++;
                $m1 = null;
                if($i < count($verses)) {
                    preg_match('/^(.*) (\d+):(\d+)$/', cleanUpScripture($verses[$i]), $m1);
                    if ($m1 && $m1[1] == $m[1] && (($m1[2] == $m[2] && $m1[3] == $m[3] + 1) || ($m1[2] == $m[2] + 1 && $m1[3] == 1))) {
                        $mf = $m1;
                        $m = $m1;
                    } else {
                        $m1 = null;
                    }
                }
            } while($m1);
            $unique_verses[] = $ms[1]." ".$ms[2].":".$ms[3].($mf?'-'.($mf[2]!=$ms[2]?$mf[2].':'.$mf[3]:($mf[3]!=$ms[3]?$mf[3]:'')):'');
        } else {
            $unique_verses[] = $verses[$i];
            $i++;
        }
    }
    print("UNIQUE VERSES:\n");
    print_r($unique_verses);
} catch(Exception $e) {
    echo $e->getMessage();
}
