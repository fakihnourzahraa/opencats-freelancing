<?php
/*
 * Added for IBC
 * EvaluationScore.php
 */

class EvaluationScore
{

    const SCALE_MIN = 0;

    const REPORT_SCALE = 5;

    const DEFAULT_MAX_RANGE = 5;

    public static function isScored($criterion)
    {
        return (isset($criterion['data_type']) && $criterion['data_type'] === 'score');
    }


    public static function maxRange($criterion)
    {
        if (!isset($criterion['max_range']) || !is_numeric($criterion['max_range']))
        {
            return self::DEFAULT_MAX_RANGE;
        }

        $max = (float) $criterion['max_range'];

        return ($max > 0) ? $max : self::DEFAULT_MAX_RANGE;
    }

    public static function parseGrade($value, $maxRange = null)
    {
        if ($value === null || $value === '' || !is_numeric($value))
        {
            return null;
        }

        $grade = (float) $value;
        $max   = ($maxRange !== null && $maxRange > 0) ? (float) $maxRange : self::DEFAULT_MAX_RANGE;

        if ($grade < self::SCALE_MIN || $grade > $max)
        {
            return null;
        }

        return $grade;
    }

    public static function normalizeWeights(array $weights)
    {
        $total = 0.0;
        foreach ($weights as $weight)
        {
            $total += max(0.0, (float) $weight);
        }

        if (count($weights) === 0)
        {
            return array();
        }

        if ($total <= 0)
        {
            $equalShare = 1.0 / count($weights);

            $out = array();
            foreach ($weights as $key => $ignored)
            {
                $out[$key] = $equalShare;
            }
            return $out;
        }

        $out = array();
        foreach ($weights as $key => $weight)
        {
            $out[$key] = max(0.0, (float) $weight) / $total;
        }
        return $out;
    }


    public static function scoreStage(array $stage)
    {
        $result = array(
            'score'       => null,
            'percent'     => null,
            'evaluators'  => array(),
            'shares'      => array(),
            'scoredCount' => 0,
        );

        $weights  = array();
        $maxes    = array();

        if (!empty($stage['criteria']))
        {
            foreach ($stage['criteria'] as $criterion)
            {
                if (!self::isScored($criterion))
                {
                    continue;
                }

                $criteriaID = (int) $criterion['instance_criteria_id'];
                $weights[$criteriaID] = max(
                    0.0, (float) (isset($criterion['weight']) ? $criterion['weight'] : 0)
                );
                $maxes[$criteriaID] = self::maxRange($criterion);
            }
        }

        $result['scoredCount'] = count($weights);
        $result['shares']      = self::normalizeWeights($weights);

        if (empty($weights) || empty($stage['evaluators']))
        {
            return $result;
        }

        if (array_sum($weights) <= 0)
        {
            foreach ($weights as $criteriaID => $ignored)
            {
                $weights[$criteriaID] = 1.0;
            }
        }

        $stageScores = array();

        foreach ($stage['evaluators'] as $evaluator)
        {
            $numerator   = 0.0;
            $denominator = 0.0;

            foreach ($weights as $criteriaID => $weight)
            {
                $raw = isset($evaluator['grades'][$criteriaID])
                     ? $evaluator['grades'][$criteriaID] : null;

                $grade = self::parseGrade($raw, $maxes[$criteriaID]);


                if ($grade === null)
                {
                    continue;
                }

                $fraction = $grade / $maxes[$criteriaID];

                $numerator   += $fraction * $weight;
                $denominator += $weight;
            }

            $evaluatorScore = ($denominator > 0) ? ($numerator / $denominator) : null;

            $result['evaluators'][(int) $evaluator['evaluator_id']] = $evaluatorScore;

            if ($evaluatorScore !== null)
            {
                $stageScores[] = $evaluatorScore;
            }
        }

        if (!empty($stageScores))
        {
            $result['score']   = array_sum($stageScores) / count($stageScores);
            $result['percent'] = $result['score'] * 100;
        }

        return $result;
    }


    public static function scoreEvaluation(array $stages)
    {
        $result = array(
            'stages'  => $stages,
            'score'   => null,
            'percent' => null,
            'shares'  => array(),
        );

        if (empty($stages))
        {
            return $result;
        }

        $stageWeights = array();

        foreach ($result['stages'] as $index => $stage)
        {
            $stageID = (int) $stage['instance_stage_id'];

            $result['stages'][$index]['scoring'] = self::scoreStage($stage);

            $stageWeights[$stageID] = max(
                0.0, (float) (isset($stage['weight']) ? $stage['weight'] : 0)
            );
        }

        $result['shares'] = self::normalizeWeights($stageWeights);

        /* Only stages that actually HAVE a score participate. An ungraded
           stage is absent from the total, not a zero in it. */
        $scoredStageWeights = array();
        $stageScoresByID    = array();

        foreach ($result['stages'] as $stage)
        {
            $stageID = (int) $stage['instance_stage_id'];
            $score   = $stage['scoring']['score'];

            if ($score === null)
            {
                continue;
            }

            $scoredStageWeights[$stageID] = max(
                0.0, (float) (isset($stage['weight']) ? $stage['weight'] : 0)
            );
            $stageScoresByID[$stageID] = $score;
        }

        if (empty($scoredStageWeights))
        {
            return $result;
        }

        if (array_sum($scoredStageWeights) <= 0)
        {
            foreach ($scoredStageWeights as $stageID => $ignored)
            {
                $scoredStageWeights[$stageID] = 1.0;
            }
        }

        $numerator   = 0.0;
        $denominator = 0.0;

        foreach ($scoredStageWeights as $stageID => $weight)
        {
            $numerator   += $stageScoresByID[$stageID] * $weight;
            $denominator += $weight;
        }

        if ($denominator > 0)
        {
            $result['score']   = $numerator / $denominator;
            $result['percent'] = $result['score'] * 100;
        }

        return $result;
    }

    public static function format($score)
    {
        return ($score === null) ? '-' : number_format($score * self::REPORT_SCALE, 2);
    }

    public static function formatPercent($score)
    {
        if ($score === null)
        {
            return '-';
        }

        return number_format($score * 100, 0) . '%';
    }


    public static function formatFull($score)
    {
        if ($score === null)
        {
            return '-';
        }

        return sprintf(
            '%s (%s/%d)',
            self::formatPercent($score),
            self::format($score),
            self::REPORT_SCALE
        );
    }
}
?>