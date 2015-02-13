<?php
namespace GettextLanguages;

/**
 * Holds the CLDR data.
 */
class CldrData
{
    /**
     * Super-special plural category: this should always be present for any language.
     * @var string
     */
    const OTHER_CATEGORY = 'other';
    /**
     * The list of the plural categories, sorted from 'zero' to 'other'.
     * @var string[]
     */
    public static $categories = array('zero', 'one', 'two', 'few', 'many', self::OTHER_CATEGORY);
    /**
     * @var string[]
     */
    private static $languageNames;
    /**
     * Returns a dictionary containing the language names.
     * The keys are the language identifiers.
     * The values are the language names in US English.
     * @return string[]
     */
    public static function getLanguageNames()
    {
        if (!isset(self::$languageNames)) {
            $json = json_decode(file_get_contents(__DIR__.'/cldr-data/main/en-US/languages.json'), true);
            self::$languageNames = $json['main']['en-US']['localeDisplayNames']['languages'];
            unset(self::$languageNames['root']);
        }

        return self::$languageNames;
    }
    /**
     * @var string[]
     */
    private static $territoryNames;
    /**
     * Return a dictionary containing the territory names (in US English).
     * The keys are the territory identifiers.
     * The values are the territory names in US English.
     * @return string[]
     */
    public static function getTerritoryNames()
    {
        if (!isset(self::$territoryNames)) {
            $json = json_decode(file_get_contents(__DIR__.'/cldr-data/main/en-US/territories.json'), true);
            self::$territoryNames = $json['main']['en-US']['localeDisplayNames']['territories'];
        }

        return self::$territoryNames;
    }
    /**
     * @var array
     */
    private static $plurals;
    /**
     * A dictionary containing the plural rules.
     * The keys are the language identifiers.
     * The values are arrays whose keys are the CLDR category names and the values are the CLDR category definition.
     * @example The English key-value pair is somethink like this:
     * <code><pre>
     * "en": {
     *     "pluralRule-count-one": "i = 1 and v = 0 @integer 1",
     *     "pluralRule-count-other": " @integer 0, 2~16, 100, 1000, 10000, 100000, 1000000, … @decimal 0.0~1.5, 10.0, 100.0, 1000.0, 10000.0, 100000.0, 1000000.0, …"
     * }
     * </pre></code>
     * @var array
     */
    public static function getPlurals()
    {
        if (!isset(self::$plurals)) {
            $json = json_decode(file_get_contents(__DIR__.'/cldr-data/supplemental/plurals.json'), true);
            self::$plurals = $json['supplemental']['plurals-type-cardinal'];
            unset(self::$plurals['root']);
        }

        return self::$plurals;
    }
    /**
     * Retrieve the CLDR plural categories for a specific language; returns null if $languageId is not valid.
     * @param string $languageId
     * @return array|null
     */
    public static function getCategoriesFor($languageId)
    {
        $matches = null;
        if (!preg_match('/^([a-z]{2,3})(?:[_\-]([A-Z][a-z]{3}))?(?:[_\-]([A-Z]{2}|[0-9]{3}))?(?:$|-)/', $languageId, $matches)) {
            return;
        }
        $languageId = $matches[1];
        // $matches[2] is the script id, we don't use it
        $territoryId = isset($matches[3]) ? $matches[3] : null;
        $variants = array();
        if (isset($territoryId)) {
            $variants[] = "$languageId-$territoryId";
        }
        $variants[] = $languageId;
        $plurals = self::getPlurals();
        $result = null;
        foreach ($variants as $variant) {
            if (isset($plurals[$variant])) {
                $result = $plurals[$variant];
                break;
            }
        }

        return $result;
    }
    /**
     * Retrieve the name of a language, as well as if a language code is deprecated in favor of another language code.
     * @param string $id The language identifier.
     * @return array|null Returns an array with the keys 'name' and 'supersededBy'. If $id is not valid returns null.
     */
    public static function getLanguageInfo($id)
    {
        $result = null;
        $matches = array();
        if (preg_match('/^([a-z]{2,3})(?:[_\-]([A-Z][a-z]{3}))?(?:[_\-]([A-Z]{2}|[0-9]{3}))?(?:$|-)/', $id, $matches)) {
            $languageId = $matches[1];
            // $matches[2] is the script id, we don't use it
            $territoryId = isset($matches[3]) ? $matches[3] : null;
            $normalizedFullId = isset($territoryId) ? "{$languageId}-{$territoryId}" : $languageId;
            $languageNames = self::getLanguageNames();
            if (isset($languageNames[$normalizedFullId])) {
                $result = array(
                    'name' => $languageNames[$normalizedFullId],
                    'supersededBy' => null,
                );
            } elseif (isset($languageNames[$languageId])) {
                $result = array(
                    'name' => $languageNames[$languageId],
                    'supersededBy' => null,
                );
                if (isset($territoryId)) {
                    $territoryNames = self::getTerritoryNames();
                    if (!isset($territoryNames[$territoryId])) {
                        return;
                    }
                    $result['name'] .= ' ('.$territoryNames[$territoryId].')';
                }
            } else {
                // The CLDR plural rules contains some language that's not defined in the language names dictionary...
                $formerCodes = array(
                    'in' => 'id', // former Indonesian
                    'iw' => 'he', // former Hebrew
                    'ji' => 'yi', // former Yiddish
                    'jw' => 'jv', // former Javanese
                    'mo' => 'ro-MD', // former Moldavian
                );
                if (isset($formerCodes[$normalizedFullId]) && isset($languageNames[$formerCodes[$normalizedFullId]])) {
                    $result = array(
                        'name' => $languageNames[$formerCodes[$normalizedFullId]],
                        'supersededBy' => str_replace('-', '_', $formerCodes[$normalizedFullId]),
                    );
                } else {
                    $byHand = array(
                        'bh' => 'Bihari',
                        'guw' => 'Gun',
                        'nah' => 'Nahuatl',
                        'smi' => 'Sami',
                    );
                    if (isset($byHand[$normalizedFullId])) {
                        $result = array(
                            'name' => $byHand[$normalizedFullId],
                            'supersededBy' => null,
                        );
                    }
                }
            }
        }

        return $result;
    }
}