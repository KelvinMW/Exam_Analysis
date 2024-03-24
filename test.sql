SELECT
    c.name AS CourseName,
    fg.name AS FormGroupName,
    fg.gibbonFormGroupID,
    iac.type AS exam_type,
    AVG(e.attainmentValue) AS mean_score,
    fg_mean.mean_score AS form_group_mean_score,
    AVG(e.attainmentValue) - COALESCE(
        fg_mean_other_type.mean_score,
        0
    ) AS mean_deviation
FROM
    gibbonCourse AS c
INNER JOIN gibbonCourseClass AS cc
ON
    c.gibbonCourseID = cc.gibbonCourseID
INNER JOIN gibbonInternalAssessmentColumn AS iac
ON
    cc.gibbonCourseClassID = iac.gibbonCourseClassID
INNER JOIN gibbonInternalAssessmentEntry AS e
ON
    iac.gibbonInternalAssessmentColumnID = e.gibbonInternalAssessmentColumnID
INNER JOIN gibbonStudentEnrolment AS se
ON
    e.gibbonPersonIDStudent = se.gibbonPersonID
INNER JOIN gibbonFormGroup AS fg
ON
    se.gibbonFormGroupID = fg.gibbonFormGroupID
INNER JOIN gibbonSchoolYear AS sy
ON
    fg.gibbonSchoolYearID = sy.gibbonSchoolYearID
LEFT JOIN(
    SELECT fg.gibbonFormGroupID,
        iac.type AS exam_type,
        AVG(e.attainmentValue) AS mean_score
    FROM
        gibbonInternalAssessmentEntry AS e
    INNER JOIN gibbonInternalAssessmentColumn AS iac
    ON
        e.gibbonInternalAssessmentColumnID = iac.gibbonInternalAssessmentColumnID
    INNER JOIN gibbonStudentEnrolment AS se
    ON
        e.gibbonPersonIDStudent = se.gibbonPersonID
    INNER JOIN gibbonFormGroup AS fg
    ON
        se.gibbonFormGroupID = fg.gibbonFormGroupID
    WHERE
        fg.gibbonFormGroupID IN(18) AND fg.gibbonSchoolYearID = 28
    GROUP BY
        fg.gibbonFormGroupID,
        iac.type
) AS fg_mean
ON
    fg.gibbonFormGroupID = fg_mean.gibbonFormGroupID AND iac.type = fg_mean.exam_type
LEFT JOIN(
    SELECT fg.gibbonFormGroupID,
        AVG(e.attainmentValue) AS mean_score
    FROM
        gibbonInternalAssessmentEntry AS e
    INNER JOIN gibbonInternalAssessmentColumn AS iac
    ON
        e.gibbonInternalAssessmentColumnID = iac.gibbonInternalAssessmentColumnID
    INNER JOIN gibbonStudentEnrolment AS se
    ON
        e.gibbonPersonIDStudent = se.gibbonPersonID
    INNER JOIN gibbonFormGroup AS fg
    ON
        se.gibbonFormGroupID = fg.gibbonFormGroupID
    WHERE
        fg.gibbonFormGroupID IN(18) AND fg.gibbonSchoolYearID = 28 AND iac.type <> 'Trial 1' AND iac.type <> 'Trial 2'
    GROUP BY
        fg.gibbonFormGroupID
) AS fg_mean_other_type
ON
    fg.gibbonFormGroupID = fg_mean_other_type.gibbonFormGroupID
WHERE
    fg.gibbonFormGroupID = 18 AND fg.gibbonSchoolYearID = 28
GROUP BY
    fg.gibbonFormGroupID,
    iac.type,
    -- Crucial change: c.name added to GROUP BY
    c.name -- Crucial change: c.name added to GROUP BY
ORDER BY
    fg.gibbonFormGroupID,
    iac.type;

--This is the query that works and the one that we are using to include variables from form. Regenerate the complete query using the placeholders that I shared which will come from the form

    SELECT
  c.name AS CourseName,
  fg.name AS FormGroupName,
  fg.gibbonFormGroupID,
  iac.type AS exam_type,
  AVG(e.attainmentValue) AS mean_score,
  fg_mean.mean_score AS form_group_mean_score,
  AVG(e.attainmentValue) - COALESCE(fg_mean_other_type.mean_score, 0) AS mean_deviation
FROM
  gibbonCourse AS c
INNER JOIN gibbonCourseClass AS cc ON c.gibbonCourseID = cc.gibbonCourseID
INNER JOIN gibbonInternalAssessmentColumn AS iac ON cc.gibbonCourseClassID = iac.gibbonCourseClassID
INNER JOIN gibbonInternalAssessmentEntry AS e ON iac.gibbonInternalAssessmentColumnID = e.gibbonInternalAssessmentColumnID
INNER JOIN gibbonStudentEnrolment AS se ON e.gibbonPersonIDStudent = se.gibbonPersonID
INNER JOIN gibbonFormGroup AS fg ON se.gibbonFormGroupID = fg.gibbonFormGroupID
INNER JOIN gibbonSchoolYear AS sy ON fg.gibbonSchoolYearID = sy.gibbonSchoolYearID
LEFT JOIN (
  SELECT fg.gibbonFormGroupID,
    iac.type AS exam_type,
    AVG(e.attainmentValue) AS mean_score
  FROM
    gibbonInternalAssessmentEntry AS e
  INNER JOIN gibbonInternalAssessmentColumn AS iac ON e.gibbonInternalAssessmentColumnID = iac.gibbonInternalAssessmentColumnID
  INNER JOIN gibbonStudentEnrolment AS se ON e.gibbonPersonIDStudent = se.gibbonPersonID
  INNER JOIN gibbonFormGroup AS fg ON se.gibbonFormGroupID = fg.gibbonFormGroupID
  WHERE
    fg.gibbonFormGroupID IN(18) AND fg.gibbonSchoolYearID = 28
  GROUP BY
    fg.gibbonFormGroupID,
    iac.type
) AS fg_mean ON fg.gibbonFormGroupID = fg_mean.gibbonFormGroupID AND iac.type = fg_mean.exam_type
LEFT JOIN (
  SELECT fg.gibbonFormGroupID,
    AVG(e.attainmentValue) AS mean_score
  FROM
    gibbonInternalAssessmentEntry AS e
  INNER JOIN gibbonInternalAssessmentColumn AS iac ON e.gibbonInternalAssessmentColumnID = iac.gibbonInternalAssessmentColumnID
  INNER JOIN gibbonStudentEnrolment AS se ON e.gibbonPersonIDStudent = se.gibbonPersonID
  INNER JOIN gibbonFormGroup AS fg ON se.gibbonFormGroupID = fg.gibbonFormGroupID
  WHERE
    fg.gibbonFormGroupID IN(18) AND fg.gibbonSchoolYearID = 28 AND iac.type <> 'Trial 1' AND iac.type <> 'Trial 2'
  GROUP BY
    fg.gibbonFormGroupID
) AS fg_mean_other_type ON fg.gibbonFormGroupID = fg_mean_other_type.gibbonFormGroupID
WHERE
  fg.gibbonFormGroupID = 18 AND fg.gibbonSchoolYearID = 28
GROUP BY
  fg.gibbonFormGroupID,
  iac.type,  -- Crucial change: c.name added to GROUP BY
  c.name     -- Crucial change: c.name added to GROUP BY
ORDER BY
  fg.gibbonFormGroupID,
  iac.type;
