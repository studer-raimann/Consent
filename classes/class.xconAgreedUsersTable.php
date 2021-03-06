<?php

require_once __DIR__.'/../vendor/autoload.php';

use srag\DIC\DICTrait;

/**
 * Class xconAgreedUsersTable
 *
 * @author Stefan Wanzenried <sw@studer-raimann.ch>
 */
class xconAgreedUsersTable extends ilTable2GUI
{

	use DICTrait;

	const PLUGIN_CLASS_NAME = ilConsentPlugin::class;

    /**
     * @var array
     */
    protected $filter = [];

    /**
     * All possible columns to display
     *
     * @var array
     */
    protected static $available_columns = [
        'login',
        'firstname',
        'lastname',
        'email',
        'agree_date',
    ];

    /**
     * Columns displayed by table with default visibility
     *
     * @var array
     */
    protected $columns = [
        'login' => true,
        'firstname' => true,
        'lastname' => true,
        'email' => true,
        'agree_date' => true,
    ];

    /**
     * @var ilObjConsent
     */
    protected $consent;


    /**
     * @param $a_parent_obj
     * @param string $a_parent_cmd
     * @param ilObjConsent $consent
     */
    public function __construct($a_parent_obj, $a_parent_cmd = "", ilObjConsent $consent)
    {
        parent::__construct($a_parent_obj, $a_parent_cmd, '');
        $this->consent = $consent;
        $this->setRowTemplate('tpl.row_generic.html', self::plugin()->directory());
        $this->setFormAction(self::dic()->ctrl()->getFormAction($a_parent_obj));
        $this->addColumns();
        $this->buildData();
    }


    /**
     * @return array
     */
    public function getSelectableColumns()
    {
        $columns = [];
        foreach ($this->columns as $column => $selectable) {
            $columns[$column] = ['txt' => self::plugin()->translate($column), 'default' => $selectable];
        }

        return $columns;
    }


    /**
     * Add selected columns to table
     *
     */
    protected function addColumns()
    {
        foreach (array_keys($this->columns) as $col) {
            if (in_array($col, self::$available_columns) && $this->isColumnSelected($col)) {
                $this->addColumn(self::plugin()->translate($col), $col);
            }
        }

    }


    /**
     * Return the formatted value
     *
     * @param string $value
     * @param string $col
     * @return string
     */
    protected function getFormattedValue($value, $col)
    {
        switch ($col) {
            case 'agree_date':
                $value = ($value) ? date($this->getDateFormat() . ', ' . 'H:i', strtotime($value)) : '-';
                break;
            default:
                $value = ($value) ? $value : "&nbsp;";
        }

        return $value;
    }


    /**
     * Return the date format which is used to format dates for all plugins
     *
     * @return string
     */
    public function getDateFormat()
    {
        switch (self::dic()->user()->getDateFormat()) {
            case ilCalendarSettings::DATE_FORMAT_DMY:
                return 'd.m.Y';
            case ilCalendarSettings::DATE_FORMAT_YMD:
                return 'Y-m-d';
            default:
                return 'Y-m-d';
        }
    }


    /**
     * @param array $a_set
     */
    protected function fillRow($a_set)
    {
        foreach (array_keys($this->columns) as $col) {
            if ($this->isColumnSelected($col)) {
                $this->tpl->setCurrentBlock('td');
                $this->tpl->setVariable('VALUE', $this->getFormattedValue($a_set[$col], $col));
                $this->tpl->parseCurrentBlock();
            }
        }
    }


    protected function fillRowCSV($a_csv, $a_set)
    {
        foreach (array_keys($this->columns) as $col) {
            if ($this->isColumnSelected($col)) {
                $value = $this->getFormattedValue($a_set[$col], $col);
                $a_csv->addColumn($this->sanitizeValueForExport($value));
            }
        }
        $a_csv->addRow();
    }


    protected function sanitizeValueForExport($value)
    {
        $value = trim(filter_var($value, FILTER_SANITIZE_STRING));

        return str_replace('&nbsp;', '', $value);
    }


    protected function fillRowExcel(ilExcel $a_excel, &$a_row, $a_set)
    {
        $col = 0;
        foreach (array_keys($this->columns) as $column) {
            if ($this->isColumnSelected($column)) {
                $value = $this->getFormattedValue($a_set[$column], $column);
                $a_excel->setCell($a_row, $col, $this->sanitizeValueForExport($value));
                $col++;
            }
        }
    }


    /**
     * Build and set data for table
     *
     */
    protected function buildData()
    {
        $rows = xconUserConsent::where([
                'obj_id' => $this->consent->getId(),
                'status' => xconUserConsent::STATUS_ACCEPTED
            ]
        )->getArray('user_id');
        $data = [];
        $ids = [];
        foreach ($rows as $row) {
            $ids[] = $row['user_id'];
        }
        if (!count($ids)) {
            $this->setData([]);

            return;
        }
        $set = self::dic()->database()->query("SELECT usr_id, login, firstname, lastname, email FROM usr_data WHERE usr_id IN (" . implode(',', $ids) . ")");
        while ($row = self::dic()->database()->fetchAssoc($set)) {
            $tmp = $row;
            $tmp['agree_date'] = $rows[$row['usr_id']]['updated_at'];
            $data[] = $tmp;
        }
        $this->setData($data);
    }
} 