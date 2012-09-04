<?php

/**
 * Tabbed Data extension.
 * (c) Vitaliy Filippov 2011
 * http://wiki.4intra.net/SimpleTable
 *
 * This extension implements a simple method to display tabular data, with a
 * far simpler markup than the standard Wiki table markup.  The goal is to
 * allow tabular data to be very easily pasted in from an external source,
 * such as a spreadsheet.  The disadvantage is that it doesn't allow for any
 * fancy formatting; for example, there is no way (as yet) to set row and cell
 * parameters, including row and cell spanning.  However, it makes a very
 * simple way to include tabular data in a Wiki.
 *
 * All you need to do is prepare your data in rows, with fields separated
 * by tab characters.  Excel's "Save as" -> "Text (Tab delimited)" function
 * saves data in this format.  Place the data inside <tab>...</tab> tags,
 * and set any table parameters inside the opening <tab> tag; eg.:
 *
 * <tab class=wikitable sep=tab>
 *   field1\tfield2\t...
 *   field1\tfield2\t...
 * </tab>
 *
 * Additional parameters allowed in the tag are:
 *   sep    Specify a different separator; can be one of:
 *          'tab', 'bar', 'space', 'spaces', 'comma', 'semicolon'
 *   head   Specify a heading; "head=top" makes the first row a heading,
 *          "head=left" makes the first column a heading, "head=topleft"
 *          does both.
 *   colN   Specify CSS style for each cell of _COLUMN_ number N.
 *          First column has N=1 (not N=0).
 *
 * Based @ the original SimpleTable
 *   http://www.mediawiki.org/wiki/Extension:SimpleTable
 *   (c) Johan the Ghost
 * Thanks for contributions to:
 *   Smcnaught
 *   Frederik Dohr
 */

$wgExtensionCredits['parserhook'][] = array(
    'name'        => 'SimpleTable',
    'author'      => 'Vitaliy Filippov',
    'url'         => 'http://wiki.4intra.net/SimpleTable',
    'description' => 'Convert tab-separated or similar data into a Wiki table',
);
$wgHooks['ParserFirstCallInit'][] = 'SimpleTable::initParser';

class SimpleTable
{
    /**
     * The permitted separators.  An array of separator style name
     * and preg pattern to match it.
     */
    private $separators = array(
        'space'     => '/ /',
        'spaces'    => '/\s+/',
        'tab'       => '/\t/',
        'comma'     => '/,/',
        'semicolon' => '/;/',
        'bar'       => '/\|/',
    );

    /**
     * Class is a singleton
     */
    public static function instance()
    {
        static $instance;
        if (!$instance)
            $instance = new SimpleTable();
        return $instance;
    }

    private function __construct()
    {
    }

    /**
     * Sets a parser hook for <tab></tab>.
     */
    public static function initParser($parser)
    {
        $parser->setHook('tab', array(self::instance(), 'hookTab'));
        return true;
    }

    /*
     * The hook function. Handles <tab></tab>.
     * Receives the table content and <tab> parameters.
     */
    public function hookTab($tableText, $argv, $parser)
    {
        // The default field separator.
        $sep = 'tab';

        // Default value for using table headings.
        $head = NULL;

        // Styles for individual columns
        $colstyle = array();

        // Build the table parameters string from the tag parameters.
        // The 'sep' and 'head' parameters are special, and are handled
        // here, not passed to the table.
        $params = '';
        foreach ($argv as $key => $value)
        {
            if ($key == 'sep')
                $sep = $value;
            elseif ($key == 'head')
                $head = $value;
            elseif (preg_match('/^col([1-9]\d*)$/s', $key, $m))
                $colstyle[$m[1]-1] = $value;
            else
                $params .= ' ' . $key . '="' . htmlspecialchars($value) . '"';
        }

        // Parse and convert the table body:

        // Parse preserving line-feeds
        $uniq = $parser->insertStripItem("\x01");
        $tableText = str_replace(array("\r\n", "\n\r", "\n", "\r"), array($uniq, $uniq, $uniq, ''), $tableText);
        $html = $parser->parse($tableText, $parser->mTitle, $parser->mOptions, false, false)->getText();
        $html = str_replace(array("\r\n", "\n\r", "\n", "\r"), array(' ', ' ', ' ', ' '), $html);
        $html = explode("\x01", trim($html, "\x01 \t\n\r"));

        // Build HTML <table> content
        $table = '';
        $headtop = strpos($head, 'top') !== false;
        $headleft = strpos($head, 'left') !== false;
        $sepre = isset($this->separators[$sep]) ? $this->separators[$sep] : false;
        if (!$sepre)
            $sepre = '/'.str_replace('/', '\\/', preg_quote($sep)).'/iu';
        foreach ($html as $i => $line)
        {
            $line = preg_replace('/<!--.*?-->/', '', $line);
            if (trim($line))
            {
                $line = preg_split($sepre, $line);
                foreach ($line as $j => &$c)
                {
                    $e = $headtop && !$i || $headleft && !$j ? 'th' : 'td';
                    $c = "<$e".(!empty($colstyle[$j]) ? " style=\"".htmlspecialchars($colstyle[$j])."\"" : '').">$c</$e>";
                }
                $table .= "<tr>" . implode('', $line) . "</tr>\n";
            }
        }
        $html = "<table$params>$table</table>";

        return $html;
    }

}
