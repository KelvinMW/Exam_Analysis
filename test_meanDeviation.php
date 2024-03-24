<?php

use PHPUnit\Framework\TestCase;

include 'meanDeviation.php';

class MeanDeviationTest extends TestCase
{

  public function testCalculateMeanDeviations()
  {

    $examType1 = 'Type 1';
    $examType2 = 'Type 2';

    $formGroupID1 = 1;
    $formGroupID2 = 2;

    $examType1MeanScores = [
      $formGroupID1 => [
        $examType1 => 70,
      ],
      $formGroupID2 => [
        $examType1 => 80,
      ]
    ];

    $examType2MeanScores = [
      $formGroupID1 => [
        $examType2 => 75,
      ],
      $formGroupID2 => [
        $examType2 => 85,
      ]
    ];

    $expected = [
      $formGroupID1 => 5,
      $formGroupID2 => 5
    ];

    $result = calculateMeanDeviations($examType1, $examType2, [$formGroupID1, $formGroupID2], $examType1MeanScores, $examType2MeanScores);

    $this->assertEquals($expected, $result);
  }

  public function testMeanDeviationsWithMissingScores()
  {

    $examType1 = 'Type 1';
    $examType2 = 'Type 2';

    $formGroupID1 = 1;
    $formGroupID2 = 2;

    $examType1MeanScores = [
      $formGroupID1 => [
        $examType1 => 70,
      ],
      $formGroupID2 => [
        $examType1 => null,
      ]
    ];

    $examType2MeanScores = [
      $formGroupID1 => [
        $examType2 => 75,
      ],
      $formGroupID2 => [
        $examType2 => 85,
      ]
    ];

    $expected = [
      $formGroupID1 => 5,
      $formGroupID2 => 0
    ];

    $result = calculateMeanDeviations($examType1, $examType2, [$formGroupID1, $formGroupID2], $examType1MeanScores, $examType2MeanScores);

    $this->assertEquals($expected, $result);
  }

  public function testMeanDeviationsWithInvalidScores()
  {

    $examType1 = 'Type 1';
    $examType2 = 'Type 2';

    $formGroupID1 = 1;
    $formGroupID2 = 2;

    $examType1MeanScores = [
      $formGroupID1 => [
        $examType1 => 'invalid',
      ],
      $formGroupID2 => [
        $examType1 => 80,
      ]
    ];

    $examType2MeanScores = [
      $formGroupID1 => [
        $examType2 => 75,
      ],
      $formGroupID2 => [
        $examType2 => 85,
      ]
    ];

    $expected = [
      $formGroupID2 => 5
    ];

    $result = calculateMeanDeviations($examType1, $examType2, [$formGroupID1, $formGroupID2], $examType1MeanScores, $examType2MeanScores);

    $this->assertEquals($expected, $result);
  }

}

?>