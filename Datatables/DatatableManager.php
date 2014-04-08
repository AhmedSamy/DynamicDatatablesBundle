<?php

namespace Hype\DynamicDatatablesBundle\Datatables;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ODM\MongoDB\DocumentRepository;
use Hype\DynamicDatatablesBundle\Datatables\DatatableException;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException,
    Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class DatatableManager
{

    protected $twig;
    protected $dataSource;
    protected $actionTemplate = 'HypeDynamicDatatablesBundle::datatableAction.html.twig';
    /* @var $logger Logger */
    protected $logger = null;

    private $columns = array();

    private $unsetColumns = array();

    private $editColumns = array();

    public function __construct(\Twig_Environment $twig)
    {
        $this->twig = $twig;
    }


    /**
     * Set selectable columns
     *
     * @param $columns
     *
     * @return $this
     */
    public function setColumns($columns)
    {
        $this->columns = $columns;

        return $this;
    }

    /**
     * Usnet columns to hide from grid
     *
     * @param $unsetColumns
     *
     * @return $this
     */
    public function setUnsetColumns($unsetColumns)
    {
        $this->unsetColumns = $unsetColumns;

        return $this;
    }

    /**
     * Edit a single column and set a callback
     *
     * @param $col
     * @param $callback
     *
     * @return $this
     * @throws DatatableException
     */
    public function editColumn($col, $callback)
    {
        if (!is_callable($callback)) {
            throw new DatatableException("second parameter should must be a callback");
        }

        if (!in_array($col, $this->columns)) {
            throw new DatatableException(sprintf(
                "col with name %s is not found, have you forget to set columns ?",
                $col
            ));
        }

        $this->editColumns[$col] = $callback;

        return $this;
    }

    /**
     * Get datatables data
     *
     * @param $get
     * @param $actionData
     * @param $aColumns
     *
     * @return array
     */
    public function datatable($get, $actionData, $aColumns = null)
    {
        $cb            = $this->getDataSource()->createQueryBuilder();
        $searchKeyword = $get['sSearch'];
        if (!isset($aColumns)) {
            $aColumns = $this->columns;
        }
        foreach ($aColumns as $col) {
            $cb->select($col);
        }
        if (isset($searchKeyword) and $searchKeyword != '') {
            foreach ($aColumns as $column) {
                if ($column != '_id') {
                    $cb->addOr($cb->expr()->field($column)->equals(new \MongoRegex('/.*' . $searchKeyword . '.*/i')));
                }
            }
        }
        if (isset($get['iDisplayStart']) && $get['iDisplayLength'] != '-1') {
            $cb->limit((int)$get['iDisplayLength']);
            $cb->skip($get['iDisplayStart']);
        }
        /*
         * Ordering
         */
        $orderedColumns = $this->getOrderedColumns();
        if (isset($get['iSortCol_0'])) {
            for ($i = 0; $i < intval($get['iSortingCols']); $i++) {
                if ($get['bSortable_' . intval($get['iSortCol_' . $i])] == "true") {
                    $cb->sort($orderedColumns[(int)$get['iSortCol_' . $i]], $get['sSortDir_' . $i]);
                }
            }
        }

        /*
         * SQL queries
         * Get data to display
         */
        $query   = $cb->hydrate(false)->getQuery();
        $rResult = $query->execute()->toArray();

        /* Data set length after filtering */
        $iFilteredTotal = count($rResult);

        /* Total data set length */
        $iTotal = $query->count();


        /*
         * Output
         */
        $output = array(
            "sEcho"                => intval($get['sEcho']),
            "iTotalRecords"        => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData"               => array()
        );

        foreach ($rResult as $aRow) {
            $row = array();
            for ($i = 0; $i < count($aColumns); $i++) {
                if (in_array($i - 1, array_keys($this->unsetColumns))) {
                    //ignore the column
                } else {
                    if (isset($aRow[$aColumns[$i]])) {
                        $cell = $aRow[$aColumns[$i]];
                        if ($aColumns[$i] == "version") {
                            /* Special output formatting for 'version' column */
                            $row[] = ($cell == "0") ? '-' : $cell;
                        } elseif ($aColumns[$i] != ' ') {
                            $row[] = $this->getCellValue($cell, $aRow, $i);
                        }

                    } else {
                        $row[] = '';
                    }
                }
            }

            $row[]              = $this->renderActions($aRow, $actionData);
            $output['aaData'][] = $row;
        }

        return $output;
    }

    /**
     * Render actions columns in the datatable
     *
     * @param $aRow
     * @param $actionData
     *
     * @return string
     */
    private function renderActions($aRow, $actionData)
    {
        return $this->twig->render(
            $this->actionTemplate,
            array(
                'actions' => $actionData,
                'id'      => $aRow['_id'],
                'data'    => $aRow
            )
        );
    }

    /**
     * Evaluate the current cell value
     *
     * @param $cell
     * @param $row
     * @param $colIndex
     *
     * @return bool|int|string
     */
    private function getCellValue($cell, $row, $colIndex)
    {
        if (isset($cell)) {
            //if column has call back
            if (in_array($this->columns[$colIndex], array_keys($this->editColumns))) {
                $callBack = $this->editColumns[$this->columns[$colIndex]];

                return $callBack($cell, $row);
            } else {
                if ($cell instanceof \MongoDate) {
                    return date('Y-m-d', $cell->sec);
                }
                if ($cell instanceof ArrayCollection) {
                    return $cell->count();
                }
                if (is_bool($cell)) {
                    return $cell === 1;
                }
            }

            return $cell;
        }

        return '';
    }

    public function setDataSource(
        ObjectRepository $dataSource
    ) {
        $this->dataSource = $dataSource;

        return $this;
    }

    /**
     *
     * @return DocumentRepository
     * @throws ServiceNotFoundException
     */
    public function getDataSource()
    {
        if (!isset($this->dataSource)) {
            throw new ServiceNotFoundException('Data provider is not defined, you have to set dataprovider first ');
        }

        return $this->dataSource;
    }

    public function setLogger(Logger $logger)
    {
        $this->logger = $logger;
    }

    private function getLogger()
    {
        if ($this->logger == null) {
            throw new ServiceUnavailableHttpException('Logger Service is not set please use setLogger on' . __CLASS__);
        }

        return $this->logger;
    }

    private function getOrderedColumns()
    {
        $orderedCols = array_values(array_diff($this->columns, $this->unsetColumns));

        return $orderedCols;
    }

}

