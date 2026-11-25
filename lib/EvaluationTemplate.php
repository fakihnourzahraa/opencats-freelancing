<?php
/*
 * Added for IBC
 * Evaluation Template Library.php
 */

class EvaluationTemplate
{
    public static $DATA_TYPES = array('text', 'date', 'number', 'score');

    /* Fallback max_range for a score criterion whose value didn't parse. */
    const DEFAULT_MAX_RANGE = 5;

    private $_db;

    public function __construct()
    {
        $this->_db     = DatabaseConnection::getInstance();
    }

    private static function _deriveGradeable($dataType)
    {
        return ($dataType === 'score') ? 1 : 0;
    }

    //returns template id for a joborder, else the generic template
    public function getTemplateID($jobOrderID)
    {
        if ($jobOrderID > 0)
        {
            $rs = $this->_db->getAssoc(sprintf(
                "SELECT template_id FROM evaluation_template
                 WHERE job_order_id = %s",
                (int) $jobOrderID
            ));
            if (!empty($rs)) return $rs['template_id'];
        }

        $rs = $this->_db->getAssoc(sprintf(
            "SELECT template_id FROM evaluation_template
             WHERE  job_order_id IS NULL"
        ));
        return !empty($rs) ? $rs['template_id'] : false;
    }

    public function addTemplate($jobOrderID)
    {
        if ($jobOrderID > 0)
        {
            $this->_db->query(sprintf(
                "INSERT INTO evaluation_template (job_order_id)
                VALUES (%s)",
                (int) $jobOrderID
            ));
            $templateID = $this->_db->getLastInsertID();

            //inherit generic
            $genericTemplateID = $this->getOwnTemplateID(0);
            if ($genericTemplateID !== false)
                $this->_copyStagesAndCriteria($genericTemplateID, $templateID);
            return ($templateID);
        }
        else
        {
            $this->_db->query(sprintf(
                "INSERT INTO evaluation_template (job_order_id)
                VALUES ( NULL)"
            ));
            return ($this->_db->getLastInsertID());
        }
    }

    //Just used for copying generic for a new template
    private function _copyStagesAndCriteria($sourceTemplateID, $destTemplateID)
    {
        $stages = $this->getStages($sourceTemplateID);

        foreach ($stages as $stage)
        {

            $this->_db->query(sprintf(
                "INSERT INTO evaluation_stage (template_id, stage_name, position, weight)
                VALUES (%s, '%s', %s, %s)",
                (int) $destTemplateID,
                $this->_db->escapeString($stage['stage_name']),
                (int) $stage['position'],
                $this->_sanitizeWeight(isset($stage['weight']) ? $stage['weight'] : 0)
            ));
            $newStageID = $this->_db->getLastInsertID();

            $criteria = $this->getCriteria($stage['stage_id']);
            foreach ($criteria as $criterion)
            {
                $dataType = isset($criterion['data_type']) ? $criterion['data_type'] : 'text';
                if (!in_array($dataType, self::$DATA_TYPES))
                {
                    $dataType = 'text';
                }

                $this->_db->query(sprintf(
                    "INSERT INTO evaluation_criteria
                        (stage_id, criteria_name, data_type, is_gradeable,
                         position, weight, max_range)
                    VALUES (%s, '%s', '%s', %s, %s, %s, %s)",
                    (int) $newStageID,

                    $this->_db->escapeString($criterion['criteria_name']),
                    $this->_db->escapeString($dataType),
                    self::_deriveGradeable($dataType),
                    (int) $criterion['position'],
                    $this->_sanitizeWeight(isset($criterion['weight']) ? $criterion['weight'] : 0),
                    $this->_sanitizeMaxRange(isset($criterion['max_range']) ? $criterion['max_range'] : self::DEFAULT_MAX_RANGE)
                ));
            }
        }
    }

    public function getStages($templateID)
    {
        return $this->_db->getAllAssoc(sprintf(
            "SELECT stage_id, stage_name, position, weight
             FROM evaluation_stage
             WHERE template_id = %s 
             ORDER BY position ASC",
            (int) $templateID
        ));
    }

    public function getCriteria($stageID)
    {
        return $this->_db->getAllAssoc(sprintf(
            "SELECT criteria_id, criteria_name, data_type, is_gradeable, position,
                    weight, max_range
             FROM evaluation_criteria
             WHERE stage_id = %s 
             ORDER BY position ASC",
            (int) $stageID
        ));
    }

    public function getOwnTemplateID($jobOrderID)
    {
        if ($jobOrderID > 0)
        {
            $rs = $this->_db->getAssoc(sprintf(
                "SELECT template_id FROM evaluation_template
                WHERE  job_order_id = %s", (int) $jobOrderID
            ));
        }
        else
        {
            $rs = $this->_db->getAssoc(sprintf(
                "SELECT template_id FROM evaluation_template
                WHERE  job_order_id IS NULL"
            ));
        }
        return (!empty($rs) ? $rs['template_id'] : false);
    }

    public function getJobOrdersWithTemplates()
    {
        $rows = $this->_db->getAllAssoc(sprintf(
            "SELECT job_order_id FROM evaluation_template
             WHERE  job_order_id IS NOT NULL"
        ));

        $map = array();
        foreach ($rows as $row)
        {
            $map[(int) $row['job_order_id']] = true;
        }
        return $map;
    }


    public function addStage($templateID, $stageName, $position, $weight = 100)
    {
        $this->_db->query(sprintf(
            "INSERT INTO evaluation_stage (template_id, stage_name, position, weight)
            VALUES (%s, '%s', %s, %s)",
            (int) $templateID,
            $this->_db->escapeString($stageName),
            (int) $position,
            $this->_sanitizeWeight($weight)
        ));
        $stageID = $this->_db->getLastInsertID();

        /* Only Comments is seeded, matching the settings page, which shows
           a new stage with a single Comments row. Position 99 keeps it
           last; getNextCriteriaPosition() ignores it when numbering new
           criteria. */
        $this->_db->query(sprintf(
            "INSERT INTO evaluation_criteria
                (stage_id, criteria_name, data_type, is_gradeable, position, weight, max_range)
            VALUES (%s, 'Comments', 'text', 0, 99, 0, %s)",
            (int) $stageID,
            $this->_sanitizeMaxRange(self::DEFAULT_MAX_RANGE)
        ));
        return ($stageID);
    }

    public function deleteStage($stageID)
    {
        $this->_db->query(sprintf(
            "DELETE FROM evaluation_criteria WHERE stage_id = %s ",
            (int) $stageID
        ));
        $this->_db->query(sprintf(
            "DELETE FROM evaluation_stage WHERE stage_id = %s ",
            (int) $stageID
        ));
    }

    public function getStageIDByName($templateID, $stageName)
    {
        $rs = $this->_db->getAssoc(sprintf(
            "SELECT stage_id FROM evaluation_stage
             WHERE template_id = %s  AND stage_name = '%s'",
            (int) $templateID,
            $this->_db->escapeString($stageName)
        ));
        return (!empty($rs) ? $rs['stage_id'] : false);
    }


    public function addCriteria($stageID, $criteriaName, $position, $dataType = 'text',
                                 $weight = 0, $isGradeable = 0, $maxRange = self::DEFAULT_MAX_RANGE)
    {
        if (!in_array($dataType, self::$DATA_TYPES))
        {
            $dataType = 'text';
        }

        $this->_db->query(sprintf(
            "INSERT INTO evaluation_criteria
                (stage_id,  criteria_name, data_type, is_gradeable,
                 position, weight, max_range)
             VALUES (%s, '%s', '%s', %s, %s, %s, %s)",
            (int) $stageID,
            $this->_db->escapeString($criteriaName),
            $this->_db->escapeString($dataType),
            self::_deriveGradeable($dataType),
            (int) $position,
            $this->_sanitizeWeight($weight),
            $this->_sanitizeMaxRange($maxRange)
        ));
        return ($this->_db->getLastInsertID());
    }

    public function deleteCriteria($criteriaID)
    {
        $this->_db->query(sprintf(
            "DELETE FROM evaluation_criteria WHERE criteria_id = %s ",
            (int) $criteriaID
        ));
    }

    public function renameStage($stageID, $newName)
    {
        $this->_db->query(sprintf(
            "UPDATE evaluation_stage SET stage_name = '%s'
            WHERE stage_id = %s ",
            $this->_db->escapeString($newName),
            (int) $stageID
        ));
    }

    public function setStageWeight($stageID, $weight)
    {
        $this->_db->query(sprintf(
            "UPDATE evaluation_stage SET weight = %s
            WHERE stage_id = %s ",
            $this->_sanitizeWeight($weight),
            (int) $stageID
        ));
    }

    public function renameCriteria($criteriaID, $newName)
    {
        $this->_db->query(sprintf(
            "UPDATE evaluation_criteria SET criteria_name = '%s'
            WHERE criteria_id = %s ",
            $this->_db->escapeString($newName),
            (int) $criteriaID
        ));
    }

    public function setCriteriaWeight($criteriaID, $weight)
    {
        $this->_db->query(sprintf(
            "UPDATE evaluation_criteria SET weight = %s
            WHERE criteria_id = %s ",
            $this->_sanitizeWeight($weight),
            (int) $criteriaID
        ));
    }

    public function setCriteriaMaxRange($criteriaID, $maxRange)
    {
        $this->_db->query(sprintf(
            "UPDATE evaluation_criteria SET max_range = %s
            WHERE criteria_id = %s ",
            $this->_sanitizeMaxRange($maxRange),
            (int) $criteriaID
        ));
    }

    public function setCriteriaGradeable($criteriaID, $isGradeable)
    {
        $this->_db->query(sprintf(
            "UPDATE evaluation_criteria SET is_gradeable = %s
            WHERE criteria_id = %s ",
            ($isGradeable ? 1 : 0),
            (int) $criteriaID
        ));
    }

    public function getCriteriaIDByName($stageID, $criteriaName)
    {
        $rs = $this->_db->getAssoc(sprintf(
            "SELECT criteria_id FROM evaluation_criteria
             WHERE stage_id = %s  AND criteria_name = '%s'",
            (int) $stageID,
            $this->_db->escapeString($criteriaName)
        ));
        return (!empty($rs) ? $rs['criteria_id'] : false);
    }

    public function getNextStagePosition($templateID)
    {
        $rs = $this->_db->getAssoc(sprintf(
            "SELECT MAX(position) AS max_pos FROM evaluation_stage
             WHERE template_id = %s ",
            (int) $templateID
        ));
        return (($rs && $rs['max_pos'] !== null) ? (int) $rs['max_pos'] + 1 : 0);
    }

    public function getNextCriteriaPosition($stageID)
    {
        $rs = $this->_db->getAssoc(sprintf(
            "SELECT MAX(position) AS max_pos FROM evaluation_criteria
            WHERE stage_id = %s  AND criteria_name != 'Comments'",
            (int) $stageID
        ));
        return ($rs && $rs['max_pos'] !== null) ? (int) $rs['max_pos'] + 1 : 0;
    }

    public function getFullTemplate($jobOrderID)
    {
        $templateID = $this->getTemplateID($jobOrderID);
        if (!$templateID) return array();

        $stages = $this->getStages($templateID);
        if (empty($stages)) return array();

        foreach ($stages as $i => $stage)
        {
            $stages[$i]['criteria'] = $this->getCriteria($stage['stage_id']);
        }
        return $stages;
    }

    public function deleteTemplate($templateID)
    {
        $stages = $this->getStages($templateID);
        foreach ($stages as $stage)
        {
            $this->_db->query(sprintf(
                "DELETE FROM evaluation_criteria WHERE stage_id = %s ",
                (int) $stage['stage_id']
            ));
        }

        $this->_db->query(sprintf(
            "DELETE FROM evaluation_stage WHERE template_id = %s ",
            (int) $templateID
        ));

        $this->_db->query(sprintf(
            "DELETE FROM evaluation_template WHERE template_id = %s ",
            (int) $templateID
        ));
    }

    public function moveStage($templateID, $stageID, $direction)
    {
        $stages = $this->getStages($templateID); //ordered by position asc
        $idx = -1;
        foreach ($stages as $i => $s)
        {
            if ((int) $s['stage_id'] === (int) $stageID)
            {
                $idx = $i;
                break;
            }
        }
        if ($idx === -1)
            return;

        $swapIdx = ($direction === 'up') ? $idx - 1 : $idx + 1;
        if ($swapIdx < 0 || $swapIdx >= count($stages))
            return;

        $posA = $stages[$idx]['position'];
        $posB = $stages[$swapIdx]['position'];

        $this->_db->query(sprintf(
            "UPDATE evaluation_stage SET position = %s WHERE stage_id = %s ",
            (int) $posB, (int) $stages[$idx]['stage_id']
        ));
        $this->_db->query(sprintf(
            "UPDATE evaluation_stage SET position = %s WHERE stage_id = %s ",
            (int) $posA, (int) $stages[$swapIdx]['stage_id']
        ));
    }

    public function moveCriteria($stageID, $criteriaID, $direction)
    {
        $criteria = $this->getCriteria($stageID); //ordered by position asc
        $idx = -1;
        foreach ($criteria as $i => $c)
        {
            if ((int) $c['criteria_id'] === (int) $criteriaID)
            {
                $idx = $i;
                break;
            }
        }
        if ($idx === -1)
            return;

        $swapIdx = ($direction === 'up') ? $idx - 1 : $idx + 1;
        if ($swapIdx < 0 || $swapIdx >= count($criteria))
            return;

        $posA = $criteria[$idx]['position'];
        $posB = $criteria[$swapIdx]['position'];

        $this->_db->query(sprintf(
            "UPDATE evaluation_criteria SET position = %s WHERE criteria_id = %s ",
            (int) $posB, (int) $criteria[$idx]['criteria_id']
        ));
        $this->_db->query(sprintf(
            "UPDATE evaluation_criteria SET position = %s WHERE criteria_id = %s ",
            (int) $posA, (int) $criteria[$swapIdx]['criteria_id']
        ));
    }

    public function changeCriteriaType($criteriaID, $dataType)
    {
        if (!in_array($dataType, self::$DATA_TYPES))
        {
            return;
        }

        $this->_db->query(sprintf(
            "UPDATE evaluation_criteria SET data_type = '%s', is_gradeable = %s
            WHERE criteria_id = %s ",
            $this->_db->escapeString($dataType),
            self::_deriveGradeable($dataType),
            (int) $criteriaID
        ));
    }

    private function _sanitizeWeight($weight)
    {
        $weight = (float) $weight;

        if ($weight < 0)       { $weight = 0; }
        if ($weight > 9999.99) { $weight = 9999.99; }

        return sprintf('%.2f', $weight);
    }

    private function _sanitizeMaxRange($maxRange)
    {
        $maxRange = is_numeric($maxRange) ? (float) $maxRange : self::DEFAULT_MAX_RANGE;

        if ($maxRange <= 0)      { $maxRange = self::DEFAULT_MAX_RANGE; }
        if ($maxRange > 9999.99) { $maxRange = 9999.99; }

        return sprintf('%.2f', $maxRange);
    }
}
?>