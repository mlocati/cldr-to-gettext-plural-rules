<?php
/**
 * Input specifications {@link http://unicode.org/reports/tr35/tr35-numbers.html#Language_Plural_Rules}
 * - n: absolute value of the source number (integer and decimals) (eg: 9.870 => 9.87)
 * - i: integer digits of n (eg: 9.870 => 9)
 * - v: number of visible fraction digits in n, with trailing zeros (eg: 9.870 => 3)
 * - w: number of visible fraction digits in n, without trailing zeros (eg: 9.870 => 2)
 * - f: visible fractional digits in n, with trailing zeros (eg: 9.870 => 870)
 * - t: visible fractional digits in n, without trailing zeros (eg: 9.870 => 87)
 // OUTPUT http://www.gnu.org/savannah-checkouts/gnu/gettext/manual/html_node/Plural-forms.html
 // - n: unsigned long int
 // - n == i
 // - v == 0
 // - w == 0
 // - f == empty
 // - t == empty
 */
error_reporting(E_ALL);
date_default_timezone_set('UTC');

$output = (isset($argv) && isset($argv[1]) && is_string($argv[1])) ? strtolower(trim($argv[1])) : '';
switch ($output) {
    case 'html':
    case 'json':
    case 'prettyjson':
    case 'php':
        break;
    default:
        echo 'Syntax: php '.basename(__FILE__).' <html|json|prettyjson|php>';
        die(1);
}

$json = json_decode(file_get_contents(__DIR__.'/cldr/main/en-US/languages.json'), true);
$languageNames = $json['main']['en-US']['localeDisplayNames']['languages'];

$json = json_decode(file_get_contents(__DIR__.'/cldr/main/en-US/territories.json'), true);
$territoryNames = $json['main']['en-US']['localeDisplayNames']['territories'];

$json = json_decode(file_get_contents(__DIR__.'/cldr/supplemental/plurals.json'), true);
$cldrPlurals = $json['supplemental']['plurals-type-cardinal'];

$plurals = array();

$rulesOrder = array('zero', 'one', 'two', 'few', 'many', 'other');
foreach ($cldrPlurals as $language => $sourceRules) {
    $normalizedLanguage = str_replace('-', '_', $language);
    if (isset($languageNames[$language])) {
        $languageName = $languageNames[$language];
    } else {
        $chunks = explode('_', $normalizedLanguage);
        $skipLanguage = false;
        if (!isset($languageNames[$chunks[0]])) {
            switch ($chunks[0]) {
                case 'bh':
                    $languageNames[$chunks[0]] = 'Bihari';
                    break;
                case 'guw':
                    $languageNames[$chunks[0]] = 'Gun';
                    break;
                case 'nah':
                    $languageNames[$chunks[0]] = 'Nahuatl';
                    break;
                case 'smi':
                    $languageNames[$chunks[0]] = 'Sami';
                    break;
                case 'in': // Former Indonesian
                case 'iw': // Former Hebrew
                case 'ji': // Former Yiddish
                case 'jw': // Former Javanese
                case 'mo': // Former Moldavian - See ro-MD
                    $skipLanguage = true;
                    break;
                default:
                    throw new Exception("Unknown language code: $language");
            }
        }
        if ($skipLanguage) {
            continue;
        }
        $languageName = $languageNames[$chunks[0]];
        switch (count($chunks)) {
            case 1:
                break;
            case 2:
                if (!isset($territoryNames[$chunks[1]])) {
                    throw new Exception("Unknown territory code: {$chunks[2]}");
                }
                $languageName .= " ({$territoryNames[$chunks[1]]})";
                break;
            default:
                throw new Exception("Unknown locale code: $language");
        }
    }
    $rulesDefs = array();
    $tests = array();
    foreach ($sourceRules as $case => $rule) {
        $x = extractRules($case, $rule);
        if (isset($rules[$x['case']])) {
            throw new Exception('Duplicated case: '.$x['case']);
        }
        $rulesDefs[$x['case']] = $x['rule'];
        if ($x['test'] !== '') {
            $tests[$x['case']] = $x['test'];
        }
    }
    if (!isset($rulesDefs['other'])) {
        throw new Exception('Missing case: other');
    }
    foreach (array_keys($rulesDefs) as $key) {
        if (!in_array($key, $rulesOrder)) {
            throw new Exception("Unknown rule: $key");
        }
    }
    uksort($rulesDefs, function ($key1, $key2) {
        global $rulesOrder;

        return array_search($key1, $rulesOrder) - array_search($key2, $rulesOrder);
    });
    $plurals[$normalizedLanguage] = array_merge(
        array('name' => $languageName),
        parseRules($normalizedLanguage, $rulesDefs, $tests)
    );
}
ksort($plurals);

switch ($output) {
    case 'html':
        ?><!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="author" content="Michele Locati">
        <title>gettext plural rules - built from CLDR</title>
        <meta name="description" content="List of all language rules for gettext .po files automatically generated from the Unicode CLDR data" />
        <link rel="stylesheet" href="http://maxcdn.bootstrapcdn.com/bootstrap/3.3.2/css/bootstrap.min.css">
        <style>
            ol.cases code {
                white-space: nowrap;
                display: block;
                clear: both;
            }
        </style>
    </head>
    <body>
        <a href="https://github.com/mlocati/cldr-to-gettext-plural-rules" class="hidden-xs"><img style="position: fixed; top: 0; right: 0; border: 0; z-index: 2000" src="https://s3.amazonaws.com/github/ribbons/forkme_right_red_aa0000.png" alt="Fork me on GitHub"></a>
        <div class="container-fluid">
            <table class="table table-bordered table-condensed table-striped">
                <thead>
                    <tr>
                        <th>Language code</th>
                        <th>Language name</th>
                        <th># plurals</th>
                        <th>Formula</th>
                        <th>Plurals</th>
                    </tr>
                </thead>
                <tbody><?php
                    foreach ($plurals as $locale => $info) {
                        ?><tr>
                            <td><?php echo h($locale); ?></td>
                            <td><?php echo h($info['name']); ?></td>
                            <td><?php echo $info['plurals']; ?></td>
                            <td><?php echo h($info['formula']); ?></td>
                            <td>
                                <ol class="cases" start="0"><?php
                                    foreach ($info['cases'] as $case) {
                                        ?><li><span><?php echo h($case)?></span><?php
                                        if (isset($info['examples'][$case])) {
                                            ?><code><?php echo h($info['examples'][$case]); ?></code><?php
                                        }
                                        ?></li><?php
                                    }
                                ?></ol>
                            </td>
                        </tr><?php
                    }
                ?></tbody>
            </table>
        </div>
    </body>
</html><?php
        break;
    case 'json':
        echo json_encode($plurals);
        break;
    case 'prettyjson':
        echo json_encode($plurals, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        break;
    case 'php':
        echo "<?php\nreturn array(";
        foreach ($plurals as $locale => $info) {
            echo "\n    '", $locale, "' => array(";
            echo "\n        'name' => '", addslashes($info['name']), "',";
            echo "\n        'plurals' => {$info['plurals']},";
            echo "\n        'formula' => '", addslashes($info['formula']), "',";
            echo "\n        'cases' => array('", implode("', '", $info['cases']), "'),";
            echo "\n        'examples' => array(";
            foreach ($info['examples'] as $case => $example) {
                echo "\n            '$case' => '", addslashes($example), "',";
            }
            echo "\n        ),";
            echo "\n    ),";
        }
        echo "\n);\n";
        break;
}
die(0);

function extractRules($case, $rule)
{
    $result = array();
    if (!preg_match('/^pluralRule-count-(.+)$/', $case, $m)) {
        throw new Exception("Bad case: $case");
    }
    $result['case'] = $m[1];
    $rule = trim(preg_replace('/\s+/', ' ', $rule));
    if (!preg_match('/^([^@]*)(?:@integer([^@]*))?(?:@decimal(?:[^@]*))?$/', $rule, $m)) {
        throw new Exception("Bad rule: $rule");
    }
    $result['rule'] = trim($m[1]);
    $result['test'] = isset($m[2]) ? trim($m[2]) : '';

    if (($result['case'] === 'other') && ($result['rule'] !== '')) {
        throw new Exception("The 'other' case should not have any rule");
    }

    return $result;
}

function parseRules($language, $rulesDefs, $tests)
{
    $originalCases = array_keys($rulesDefs);
    if (count($originalCases) === 1) {
        // Special case: 1 plural rule
        if ($rulesDefs['other'] !== '') {
            throw new Exception('Bad formula for the case of one plural: '.$rulesDefs['other']);
        }
        $finalCases = $originalCases;
        $formula = '0';
    } else {
        $finalCases = array();
        $formulaForCase = array();
        for ($i = 0; $i < count($originalCases) - 1; $i++) {
            $case = $originalCases[$i];
            try {
                $formulaParser = new FormulaParser($rulesDefs[$case]);
                $f = $formulaParser->getFormula();
            } catch (Exception $x) {
                throw new Exception("Error parsing the '$case' case for '$language' with the formula '{$rulesDefs[$case]}':\n".$x->getMessage());
            }
            if ($f === false) {
                if (isset($tests[$case])) {
                    throw new Exception("The rule '$case' for '$language' with the formula '{$rulesDefs[$case]}' has been calculated that should occur, but we have the test for it: ".$tests[$case]);
                }
            } else {
                $finalCases[] = $case;
                $formulaForCase[] = $f;
            }
        }
        $finalCases[] = $originalCases[$i];
        if (count($finalCases) < 2) {
            throw new Exception('Unhandled excessive simplification');
        }
        if (count($finalCases) === 2) {
            $formula = ManualReducer::reduce(reverseFinalFormula($formulaForCase[0], $language));
        } else {
            // We need to add some parenthesis. In C it's not mandatory, in other languages (like PHP) it is.
            // Example: '(0 == 0) ? 0 : (0 == 1) ? 1 : 2' results in 0 in C (see http://codepad.org/Epw5WkmJ ) but in 2 in PHP (see http://3v4l.org/QAAnA )
            foreach (array_keys($formulaForCase) as $i) {
                $formulaForCase[$i] = ManualReducer::reduce($formulaForCase[$i]);
                if (!preg_match('/^\([^()]+\)$/', $formulaForCase[$i])) {
                    $formulaForCase[$i] = '('.$formulaForCase[$i].')';
                }
            }
            $formula = strval(count($finalCases) - 1);
            for ($i = count($finalCases) - 2; $i > 0; $i--) {
                $formula = '('.$formulaForCase[$i].' ? '.$i.' : '.$formula.')';
            }
            $formula = $formulaForCase[0].' ? 0 : '.$formula;
        }
    }
    $result = array(
        'plurals' => count($finalCases),
        'formula' => $formula,
        'cases' => $finalCases,
        'examples' => $tests,
    );
    try {
        foreach ($tests as $rule => $numbers) {
            Tester::run($result, $numbers, $rule);
        }
    } catch (Exception $x) {
        throw new Exception("Test failed for '$language':\n".$x->getMessage());
    }

    return $result;
}

class FormulaParser
{
    private $originalRules;
    private $orCases = array();
    public function __construct($rules)
    {
        if (strpbrk($rules, '()') !== false) {
            throw new Exception('Parenthesis handling not implemented');
        }
        $this->originalRules = $rules;
        $s = $rules;
        while (true) {
            $p = strpos($s, ' or ');
            if ($p === false) {
                break;
            }
            $this->orCases[] = new AndCases(substr($s, 0, $p));
            $s = substr($s, $p + strlen(' or '));
        }
        $this->orCases[] = new AndCases($s);
    }
    public function getFormula()
    {
        $formulas = array();
        $someFalse = true;
        foreach ($this->orCases as $or) {
            $f = $or->getFormula();
            if ($f === true) {
                throw new Exception('Error parsing formula '.$this->originalRules.': always true');
            } elseif ($f === false) {
                $someFalse = true;
            } else {
                $formulas[] = $f;
            }
        }
        if (empty($formulas)) {
            if ($someFalse) {
                return false;
            } else {
                throw new Exception('Error parsing formula '.$this->originalRules.': always false');
            }
        }

        return implode(' || ', $formulas);
    }
}

class AndCases
{
    private $originalRules;
    private $atoms = array();
    public function __construct($rules)
    {
        $this->originalRules = $rules;
        $s = $rules;
        while (true) {
            $p = strpos($s, ' and ');
            if ($p === false) {
                break;
            }
            $this->atoms[] = new Atom(substr($s, 0, $p));
            $s = substr($s, $p + strlen(' and '));
        }
        $this->atoms[] = new Atom($s);
    }
    public function getFormula()
    {
        $someTrue = false;
        $formulas = array();
        foreach ($this->atoms as $atom) {
            $f = $atom->getFormula();
            if ($f === false) {
                return false;
            } elseif ($f === true) {
                $someTrue = true;
            } else {
                $formulas[] = $f;
            }
        }
        if (empty($formulas)) {
            if ($someTrue) {
                return true;
            } else {
                throw new Exception('Error parsing formula '.$this->originalRules);
            }
        }
        $result = implode(' && ', $formulas);
        // Special case simplification
        switch ($result) {
            case 'n >= 0 && n <= 2 && n != 2':
                $result = 'n == 0 || n == 1';
                break;
        }

        return $result;
    }
}
class Atom
{
    private $originalRule;
    public function __construct($rule)
    {
        $this->originalRule = $rule;
    }
    public function getFormula()
    {
        $rule = $this->originalRule;
        $rule = str_replace('i', 'n', $rule);
        $rule = str_replace(' = ', ' == ', $rule);
        if (preg_match('/^n( % \d+)? (!=|==) \d+$/', $rule)) {
            return $rule;
        }
        if (preg_match('/^n( % \d+)? (!=|==) \d+(,\d+|\.\.\d+)+$/', $rule)) {
            return self::expandRule($rule);
        }
        if (preg_match('/^(?:v|w) == (\d+)$/', $rule, $m)) { // v == 0, w == 0
            return (intval($m[1]) === 0) ? true : false;
        }
        if (preg_match('/^(?:v|w) != (\d+)$/', $rule, $m)) { // v == 0, w == 0
            return (intval($m[1]) === 0) ? false : true;
        }
        if (preg_match('/^(?:f|t) % \d+ == (\d+)(?:\.\.\d+)?$/', $rule, $m) && (intval($m[1]) !== 0)) { // f == empty, t == empty
            return false;
        }
        if (preg_match('/^(?:f|t) == (\d+)$/', $rule, $m)) { // f == empty, t == empty
            return (intval($m[1]) === 0) ? true : false;
        }
        if (preg_match('/^(?:f|t) != (\d+)$/', $rule, $m)) { // f == empty, t == empty
            return (intval($m[1]) === 0) ? false : true;
        }
        throw new Exception("Unhandled case: $rule");
    }
    private static function expandRule($rule)
    {
        if (preg_match('/^(n(?: % \d+)?) (==|!=) (\d+(?:\.\.\d+|,\d+)+)$/', $rule, $m)) {
            $what = $m[1];
            $op = $m[2];
            $chunks = array();
            foreach (explode(',', $m[3]) as $range) {
                $chunk = null;
                if ((!isset($chunk)) && preg_match('/^\d+$/', $range)) {
                    $chunk = "$what $op $range";
                }
                if ((!isset($chunk)) && preg_match('/^(\d+)\.\.(\d+)$/', $range, $m)) {
                    $from = intval($m[1]);
                    $to = intval($m[2]);
                    if (($to - $from) === 1) {
                        switch ($op) {
                            case '==':
                                $chunk = "($what == $from || $what == $to)";
                                break;
                            case '!=':
                                $chunk = "$what != $from && $what == $to";
                                break;
                        }
                    } else {
                        switch ($op) {
                            case '==':
                                $chunk = "$what >= $from && $what <= $to";
                                break;
                            case '!=':
                                $chunk = "($what < $from || $what > $to)";
                                break;
                        }
                    }
                }
                if (!isset($chunk)) {
                    throw new Exception("Unhandled range '$range' in $rule");
                }
                $chunks[] = $chunk;
            }
            if (count($chunks) === 1) {
                return $chunks[0];
            }
            switch ($op) {
                case '==':
                    return '('.implode(' || ', $chunks).')';break;
                case '!=':
                    return implode(' && ', $chunks);
            }
        }
        throw new Exception("Unable to expand rule '$rule'");
    }
}

function reverseFinalFormula($formula, $language)
{
    if (preg_match('/^n( % \d+)? == \d+(\.\.\d+|,\d+)*?$/', $formula)) {
        return str_replace(' == ', ' != ', $formula);
    }
    if (preg_match('/^\(?n == \d+ \|\| n == \d+\)?$/', $formula)) {
        return trim(str_replace(array(' == ', ' || '), array(' != ', ' && '), $formula), '()');
    }
    if (preg_match('/^(n(?: % \d+)?) == (\d+) && (n(?: % \d+)?) != (\d+)$/', $formula, $m)) {
        return "{$m[1]} != {$m[2]} || {$m[3]} == {$m[4]}";
    }
    switch ($formula) {
        case '(n == 1 || n == 2 || n == 3) || n % 10 != 4 && n % 10 != 6 && n % 10 != 9':
            return 'n != 1 && n != 2 && n != 3 && (n % 10 == 4 || n % 10 == 6 || n % 10 == 9)';
        case '(n == 0 || n == 1) || n >= 11 && n <= 99':
            return 'n >= 2 && (n < 11 || n > 99)';
    }
    throw new Exception("Unhandled formula reverse for '$language': $formula");
}

class Tester
{
    private static function expandNumbers($numbers)
    {
        $result = array();
        if (substr($numbers, -strlen(', …')) === ', …') {
            $numbers = substr($numbers, 0, strlen($numbers) -strlen(', …'));
        }
        foreach (explode(',', str_replace(' ', '', $numbers)) as $range) {
            if (preg_match('/^\d+$/', $range)) {
                $result[] = intval($range);
            } elseif (preg_match('/^(\d+)~(\d+)$/', $range, $m)) {
                $from = intval($m[1]);
                $to = intval($m[2]);
                $delta = $to - $from;
                $step = (int) max(1, $delta / 100);
                for ($i = $from; $i < $to; $i += $step) {
                    $result[] = $i;
                }
                $result[] = $to;
            } else {
                throw new Exception("Unhandled test range '$range' in '$numbers'");
            }
        }
        if (empty($result)) {
            throw new Exception("No test numbers from '$numbers'");
        }

        return $result;
    }
    public static function run($defs, $numbers, $expectedCase)
    {
        $expectedCaseIndex = in_array($expectedCase, $defs['cases']);
        foreach (self::expandNumbers($numbers) as $number) {
            $formula = preg_replace('/\bn\b/', strval($number), $defs['formula']);
            if (!preg_match('/^[\d %!=<>&\|()?:]+$/', $formula)) {
                throw new Exception("Invalid formula '$formula' (original: '{$defs['formula']}')");
            }
            $caseIndex = @eval("return (($formula) === true) ? 1 : ((($formula) === false) ? 0 : ($formula));");
            if (!is_int($caseIndex)) {
                throw new Exception("Error getting the result from '{$defs['formula']}'");
            }
            if (!isset($defs['cases'][$caseIndex])) {
                throw new Exception("The formula '{$defs['formula']}' evaluated for $number gave an out-of-range case index ($caseIndex)");
            }
            $case = $defs['cases'][$caseIndex];
            if ($case !== $expectedCase) {
                throw new Exception("The formula '{$defs['formula']}' evaluated for $number resulted in '$case' ($caseIndex) instead of '$expectedCase' ($expectedCaseIndex)");
            }
        }
    }
}

function h($str)
{
    return htmlspecialchars($str, ENT_COMPAT, 'UTF-8');
}

class ManualReducer
{
    private static $map = array(
        'n != 0 && n != 1'              =>  'n > 1' ,
        '(n == 0 || n == 1) && n != 0'  =>  'n == 1',
    );
    public static function reduce($formula)
    {
        return isset(self::$map[$formula]) ? self::$map[$formula] : $formula;
    }
}
