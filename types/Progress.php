<?php
/**
 * DokuWiki Plugin structprogress 
 * Most Code is taken from decimal Type: https://github.com/cosmocode/dokuwiki-plugin-struct/blob/5c37a46b990a9bc0e314c8faa228db6012387b5f/types/Decimal.php 
 *
 * @author: saggi <saggi@gmx.de>
 */

namespace dokuwiki\plugin\structprogress\types;

use dokuwiki\plugin\struct\meta\QueryBuilder;
use dokuwiki\plugin\struct\meta\QueryBuilderWhere;
use dokuwiki\plugin\struct\meta\ValidationException;
use dokuwiki\plugin\struct\types\AbstractMultiBaseType;

class Progress extends AbstractMultiBaseType
{
    
    protected $config = array(
        'max' => '100',
        'prefix' => '',
        'postfix' => '',
        'type' => 'default',
    );

    /**
     * @inheritDoc
     */
    public function validate($rawvalue)
    {
        $rawvalue = parent::validate($rawvalue);
        $rawvalue = str_replace(',', '.', $rawvalue); // we accept both
        $minvalue = 0;
        $maxvalue = 100;
        if ($this->config['max'] !== '' && floatval($this->config['max']) > 0){
            $maxvalue = floatval($this->config['max']);
        }

        if ((string)$rawvalue != (string)floatval($rawvalue)) {
            throw new ValidationException('Decimal needed');
        }

        if ($minvalue !== 0 && floatval($rawvalue) < $minvalue) {
            throw new ValidationException('Decimal min',$minvalue);
        }
        
        if ($maxvalue !== 0 && floatval($rawvalue) > $maxvalue) {
            throw new ValidationException('Decimal max', $maxvalue);
        }

        return $rawvalue;
    }

    /**
     * @inheritDoc
     */
    public function renderValue($value, \Doku_Renderer $R, $mode)
    {
        if ($mode == 'xhtml') {
            $maxvalue = 100;
            if ($this->config['max'] !== '' && floatval($this->config['max']) > 0){
                $maxvalue = floatval($this->config['max']);
            }
            $progress = 100*$value/$maxvalue;
            $R->doc .= '<div title="' . hsc($value) . '" class="struct_progress-background_'.hsc($this->config['type']).'">';
            $R->doc .= '<div title="' . hsc($value) . '" style="width: '.$progress.'% ;"
                        class="struct_progress_'.hsc($this->config['type']).'"><p>'.hsc($this->config['prefix'].$value.$this->config['postfix']).'</p></div>';
            $R->doc .= '</div>';
        } else {
            $R->cdata($value);
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function renderMultiValue($values, \Doku_Renderer $R, $mode)
    {
        if ($mode == 'xhtml') {
            $R->doc .= '<div title="Multi-Value" class="struct_progress-multi">';
            foreach ($values as $value) {
                $this->renderValue($value, $R, $mode);
            }
            $R->doc .= '</div>';
        } else {
            $R->cdata(join(', ', $values));
        }
        return true;
    }

    /**
     * Works like number_format but keeps the decimals as is
     *
     * @link http://php.net/manual/en/function.number-format.php#91047
     * @author info at daniel-marschall dot de
     * @param float $number
     * @param string $dec_point
     * @param string $thousands_sep
     * @return string
     */
    protected function formatWithoutRounding($number, $dec_point, $thousands_sep)
    {
        $was_neg = $number < 0; // Because +0 == -0

        $tmp = explode('.', $number);
        $out = number_format(abs(floatval($tmp[0])), 0, $dec_point, $thousands_sep);
        if (isset($tmp[1])) $out .= $dec_point . $tmp[1];

        if ($was_neg) $out = "-$out";

        return $out;
    }

    /**
     * Decimals need to be casted to the proper type for sorting
     *
     * @param QueryBuilder $QB
     * @param string $tablealias
     * @param string $colname
     * @param string $order
     */
    public function sort(QueryBuilder $QB, $tablealias, $colname, $order)
    {
        $QB->addOrderBy("CAST($tablealias.$colname AS DECIMAL) $order");
    }

    /**
     * Decimals need to be casted to proper type for comparison
     *
     * @param QueryBuilderWhere $add
     * @param string $tablealias
     * @param string $colname
     * @param string $comp
     * @param string|\string[] $value
     * @param string $op
     */
    public function filter(QueryBuilderWhere $add, $tablealias, $colname, $comp, $value, $op)
    {
        $add = $add->where($op); // open a subgroup
        $add->where('AND', "$tablealias.$colname != ''"); // make sure the field isn't empty
        $op = 'AND';

        /** @var QueryBuilderWhere $add Where additionional queries are added to */
        if (is_array($value)) {
            $add = $add->where($op); // sub where group
            $op = 'OR';
        }

        foreach ((array)$value as $item) {
            $pl = $add->getQB()->addValue($item);
            $add->where($op, "CAST($tablealias.$colname AS DECIMAL) $comp CAST($pl AS DECIMAL)");
        }
    }

    /**
     * Only exact matches for numbers
     *
     * @return string
     */
    public function getDefaultComparator()
    {
        return '=';
    }
}

