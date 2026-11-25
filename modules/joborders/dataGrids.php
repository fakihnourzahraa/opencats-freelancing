<?php 
/*
 * OpenCATS
 *
 * Portions Copyright (C) 2005-2007 Cognizo Technologies, Inc.
 * Originally released as part of CATS Standard Edition under the
 * CATS Public License 1.1a.
 *
 * See LICENSE.md.
 */
 
include_once(LEGACY_ROOT . '/lib/JobOrders.php');
include_once(LEGACY_ROOT . '/lib/Hooks.php');
include_once(LEGACY_ROOT . '/lib/Width.php');
include_once(LEGACY_ROOT . '/lib/Candidates.php');

class JobOrdersListByViewDataGrid extends JobOrdersDataGrid
{
    public function __construct($parameters, $misc)
    {
        /* Pager configuration. */
        $this->_tableWidth = new Width(100, '%');
        $this->_defaultAlphabeticalSortBy = 'title';
        $this->ajaxMode = false;
        $this->showExportCheckboxes = true; //BOXES WILL NOT APPEAR UNLESS SQL ROW exportID IS RETURNED!
        $this->showActionArea = true;
        $this->showChooseColumnsBox = true;
        $this->allowResizing = true;

        $this->defaultSortBy = 'dateCreatedSort';
        $this->defaultSortDirection = 'DESC';
   
        $this->_defaultColumns = array(
            array('name' => 'Attachments', 'width' => 10),
            array('name' => 'ID', 'width' => 26),   
            array('name' => 'Title', 'width' => 170),
            array('name' => 'Company', 'width' => 135),
            array('name' => 'Type', 'width' => 30),
            array('name' => 'Status', 'width' => 40),
            array('name' => 'Created', 'width' => 55),
            array('name' => 'Age', 'width' => 30),
            array('name' => 'Submitted', 'width' => 18),
            array('name' => 'Pipeline', 'width' => 18),
            array('name' => 'Recruiter', 'width' => 65),
            array('name' => 'Owner', 'width' => 55),
        );
   
        if (!eval(Hooks::get('JOBORDERS_DATAGRID_DEFAULTS'))) return;
   
        parent::__construct("joborders:JobOrdersListByViewDataGrid", 
                             $parameters, $misc
                        );
    }
    

    /**
     * Adds more options to the action area on the pager.  Overloads 
     * DataGrid Inner Action Area function.
     *
     * @return html innerActionArea commands.
     */    
    public function getInnerActionArea()
    {
        $html = '';

        $html .= $this->getInnerActionAreaItemPopup('Add To List', CATSUtility::getIndexName().'?m=lists&amp;a=addToListFromDatagridModal&amp;dataItemType='.DATA_ITEM_JOBORDER, 450, 350);
        if ($_SESSION['CATS']->getAccessLevel('export') >= ACCESS_LEVEL_SA)
        {
            $html .= $this->getInnerActionAreaItem('Export', CATSUtility::getIndexName().'?m=export&amp;a=exportByDataGrid');
        }

        $html .= parent::getInnerActionArea();

        return $html;

    }
}

class joborderSavedListByViewDataGrid extends JobOrdersDataGrid
{
    public function __construct($parameters, $misc)
    {
        /* Pager configuration. */
        $this->_tableWidth = new Width(100, '%');
        $this->_defaultAlphabeticalSortBy = 'title';
        $this->ajaxMode = false;
        $this->showExportCheckboxes = true; //BOXES WILL NOT APPEAR UNLESS SQL ROW exportID IS RETURNED!
        $this->showActionArea = true;
        $this->showChooseColumnsBox = true;
        $this->allowResizing = true;

        $this->defaultSortBy = 'dateCreatedSort';
        $this->defaultSortDirection = 'DESC';
   
        $this->_defaultColumns = array(
            array('name' => 'Attachments', 'width' => 10),
            array('name' => 'ID', 'width' => 26),   
            array('name' => 'Title', 'width' => 170),
            array('name' => 'Company', 'width' => 135),
            array('name' => 'Type', 'width' => 30),
            array('name' => 'Status', 'width' => 40),
            array('name' => 'Created', 'width' => 55),
            array('name' => 'Age', 'width' => 30),
            array('name' => 'Submitted', 'width' => 18),
            array('name' => 'Pipeline', 'width' => 18),
            array('name' => 'Recruiter', 'width' => 65),
            array('name' => 'Owner', 'width' => 55),
        );
   
        if (!eval(Hooks::get('JOBORDERS_DATAGRID_DEFAULTS'))) return;
   
        parent::__construct("joborders:joborderSavedListByViewDataGrid", 
                             $parameters, $misc
                        );
    }
    

    /**
     * Adds more options to the action area on the pager.  Overloads 
     * DataGrid Inner Action Area function.
     *
     * @return html innerActionArea commands.
     */    
    public function getInnerActionArea()
    {
        $html = '';

        $html .= $this->getInnerActionAreaItemPost('Remove From This List', CATSUtility::getIndexName().'?m=lists&amp;a=removeFromListDatagrid&amp;dataItemType='.DATA_ITEM_JOBORDER.'&amp;savedListID='.$this->getMiscArgument(), false);
        if ($_SESSION['CATS']->getAccessLevel('export') >= ACCESS_LEVEL_SA)
        {
            $html .= $this->getInnerActionAreaItem('Export', CATSUtility::getIndexName().'?m=export&amp;a=exportByDataGrid');
        }

        $html .= parent::getInnerActionArea();

        return $html;

    }
}

class PipelineCandidatesDataGrid extends CandidatesDataGrid
{
    public function __construct($parameters, $misc)
    {
  $this->_pipelineJobOrderID = is_numeric($misc) ? (int) $misc : null;
        /* Pager configuration. */
        $this->_tableWidth = new Width(100, '%');
        $this->_defaultAlphabeticalSortBy = 'lastName';
        $this->ajaxMode = false;
        $this->showExportCheckboxes = true;
        $this->showActionArea = true;
        $this->showChooseColumnsBox = true;
        $this->allowResizing = true;
        $this->defaultSortBy = 'dateModifiedSort';
        $this->defaultSortDirection = 'DESC';
        $this->_defaultColumns = array(
            array('name' => 'Attachments', 'width' => 31),
            array('name' => 'First Name', 'width' => 75),
            array('name' => 'Last Name', 'width' => 85),
            array('name' => 'City', 'width' => 75),
            array('name' => 'State', 'width' => 50),
            array('name' => 'Key Skills', 'width' => 215),
            array('name' => 'Owner', 'width' => 65),
            array('name' => 'Created', 'width' => 60),
            array('name' => 'Modified', 'width' => 60),
        );
        parent::__construct(
            'joborders:PipelineCandidatesDataGrid',
             $parameters, 0   // always 0 — preserves existing instance name/md5
        );
        $this->_classColumns['Added'] = array(
            'pagerWidth'  => 60,
            'filterTypes' => '=d>=d<==',
        );
    }
}
class PipelineExportDataGrid extends PipelineCandidatesDataGrid
{
    private static $_pipelineColMap = array(
        'firstName'           => 'First Name',
        'lastName'            => 'Last Name',
        'state'               => 'State',
        'city'                => 'City',
        'zip'                 => 'Zip',
        'address'             => 'Address',
        'dateCreatedInt'      => 'Created',
        'status'              => 'Status',
        'candidateEmail'      => 'E-Mail',
        'candidateEmail2'     => '2nd E-Mail',
        'phoneHome'           => 'Home Phone',
        'phoneCell'           => 'Cell Phone',
        'phoneWork'           => 'Work Phone',
        'keySkills'           => 'Key Skills',
        'currentEmployer'     => 'Current Employer',
        'currentPay'          => 'Current Pay',
        'desiredPay'          => 'Desired Pay',
        'canRelocate'         => 'Can Relocate',
        'source'              => 'Source',
        'webSite'             => 'Web Site',
        'notes'               => 'Misc Notes',
        'dateAvailable'       => 'Available',
        'dateModified'        => 'Modified'
    );
    protected function buildColumns()
    {
        parent::buildColumns();
        $visibleCols = isset($_SESSION['pipelineCols'])
            ? $_SESSION['pipelineCols']
            : array('firstName', 'lastName', 'state', 'dateCreatedInt', 'status');
        $newCurrentColumns = array();
        foreach ($visibleCols as $pipelineKey)
        {
            if (!isset(self::$_pipelineColMap[$pipelineKey]))
                continue; // match, addedByAbbrName, lastActivity, action have no DataGrid equivalent
            $dgColName = self::$_pipelineColMap[$pipelineKey];
            if (!isset($this->_classColumns[$dgColName]))
                continue;
            $newCurrentColumns[] = array(
                'name'  => $dgColName,
                'width' => isset($this->_classColumns[$dgColName]['pagerWidth'])
                               ? $this->_classColumns[$dgColName]['pagerWidth'] : 80,
                'data'  => $this->_classColumns[$dgColName],
            );
        }
        if (!empty($newCurrentColumns))
            $this->_currentColumns = $newCurrentColumns;
    }
}


?>
