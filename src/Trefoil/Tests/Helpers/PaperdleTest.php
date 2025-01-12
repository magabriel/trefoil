<?php

declare(strict_types=1);

namespace Trefoil\Tests\Helpers;

use Trefoil\Helpers\Paperdle;
use PHPUnit\Framework\TestCase;

class PaperdleTest extends TestCase
{
    public function testInitValidWord(): void
    {
        $paperdle = new Paperdle();
        $paperdle->generate('CLAVE');

        $evaluationTable = $paperdle->getEvaluationTable();
        $this->assertIsArray($evaluationTable);
        $this->assertCount(5, $evaluationTable); // 5 letters
        $this->assertEmpty($paperdle->getErrors());
        $this->assertEmpty($paperdle->getWarnings());
    }

    public function testInitInvalidWord(): void
    {
        $paperdle = new Paperdle();
        $paperdle->generate('BAD');

        $errors = $paperdle->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('The word to guess must have at least 5 letters', $errors[0]);
    }

    public function testGetEvaluationTable(): void
    {
        $paperdle = new Paperdle();
        $paperdle->generate('CLAVE');
        $actual = $paperdle->getEvaluationTableAsText();

        $expected = <<<TEXT
           A B C D E F G H I J K L M N O P Q R S T U V W X Y Z
           - - - - - - - - - - - - - - - - - - - - - - - - - -
        1: ? # = # ? # # # # # # ? # # # # # # # # # ? # # # #
        2: ? # ? # ? # # # # # # = # # # # # # # # # ? # # # #
        3: = # ? # ? # # # # # # ? # # # # # # # # # ? # # # #
        4: ? # ? # ? # # # # # # ? # # # # # # # # # = # # # #
        5: ? # ? # = # # # # # # ? # # # # # # # # # ? # # # #
        TEXT;

        $this->assertEquals([], $paperdle->getErrors());
        $this->assertEquals([], $paperdle->getWarnings());

        $this->assertEquals($expected, $actual);
    }

    public function testGetEvaluationTableNonEnglish(): void
    {
        $paperdle = new Paperdle();
        $paperdle->generate('PEÑAS', 'ABCDEFGHIJKLMNÑOPQRSTUVWXYZ');
        $actual = $paperdle->getEvaluationTableAsText();

        $expected = <<<TEXT
           A B C D E F G H I J K L M N Ñ O P Q R S T U V W X Y Z
           - - - - - - - - - - - - - - - - - - - - - - - - - - -
        1: ? # # # ? # # # # # # # # # ? # = # # ? # # # # # # #
        2: ? # # # = # # # # # # # # # ? # ? # # ? # # # # # # #
        3: ? # # # ? # # # # # # # # # = # ? # # ? # # # # # # #
        4: = # # # ? # # # # # # # # # ? # ? # # ? # # # # # # #
        5: ? # # # ? # # # # # # # # # ? # ? # # = # # # # # # #
        TEXT;

        $this->assertEquals([], $paperdle->getErrors());
        $this->assertEquals([], $paperdle->getWarnings());

        $this->assertEquals($expected, $actual);
    }

    public function testGetRandomizerTable(): void
    {
        $paperdle = new Paperdle();
        $paperdle->generate('CLAVE');
        $actual = $paperdle->getRandomizerTableAsText();

        // NOTE: Expected values are fixed because the randomizer is seeded after the hidden word.
        $expected = <<<TEXT
            A  B  C  D  E  F  G  H  I  J  K  L  M  N  O  P  Q  R  S  T  U  V  W  X  Y  Z
           -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- --
        1: M3 V2 B2 J1 G4 R4 T2 J4 K1 M1 W1 Z1 B1 W3 S4 Z3 D2 R5 K5 U3 C2 X5 N4 R3 P1 X2
        2: B5 G1 W4 N2 H2 X1 O2 O3 H5 I1 E4 D3 E3 L4 I5 O1 H3 D5 S2 Y5 P4 Q3 S1 W5 A2 X4
        3: Z5 G3 C4 Y2 G5 K3 C1 R2 F3 E1 D1 I3 K4 P5 L3 O4 A1 A4 K2 Y3 N5 M4 E2 Q2 Q4 N3
        4: Q1 D4 P3 C5 B4 I2 Q5 H1 B3 U4 Z2 H4 U5 R1 V4 M5 U2 T5 L5 T1 V5 L2 U1 J5 J3 T4
        5: Y1 O5 F1 T3 S3 F2 Y4 J2 Z4 L1 M2 V3 E5 X3 A3 I4 N1 V1 F4 G2 C3 S5 F5 W2 A5 P2
        TEXT;

        $this->assertEquals([], $paperdle->getErrors());
        $this->assertEquals([], $paperdle->getWarnings());

        $this->assertEquals($expected, $actual);
    }

    public function testGeRandomizerTableNonEnglish(): void
    {
        $paperdle = new Paperdle();
        $paperdle->generate('PEÑAS', 'ABCDEFGHIJKLMNÑOPQRSTUVWXYZ');
        $actual = $paperdle->getRandomizerTableAsText();

        // NOTE: Expected values are fixed because the randomizer is seeded after the hidden word.
        $expected = <<<TEXT
            A  B  C  D  E  F  G  H  I  J  K  L  M  N  Ñ  O  P  Q  R  S  T  U  V  W  X  Y  Z
           -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- --
        1: Q2 R5 S4 G1 S2 P1 T3 V3 K5 G5 A1 B1 M2 W5 U4 G3 O3 U3 O1 O2 I5 F2 H2 L5 C4 M4 E4
        2: T2 K2 T5 A3 B2 F3 Y3 W2 N3 R1 G4 V4 C2 I1 W4 N5 Z1 Ñ1 Ñ5 C3 P4 J2 A5 M5 J5 L4 I4
        3: J3 J1 S5 B4 Z3 Z5 H1 O4 D5 R2 D4 U2 F4 W1 A4 H5 X2 C1 Q4 A2 J4 M1 E2 X4 U5 Y1 D2
        4: R4 V5 Ñ4 Y2 P3 P5 K1 F5 H3 Q5 C5 B5 Ñ3 I3 E1 T4 D1 L3 H4 V1 B3 X1 Y5 R3 S3 M3 E5
        5: K3 X5 D3 L2 N4 O5 S1 N1 Ñ2 G2 Q1 K4 E3 X3 L1 W3 V2 Z4 N2 Z2 I2 U1 Y4 P2 Q3 T1 F1
        TEXT;

        $this->assertEquals([], $paperdle->getErrors());
        $this->assertEquals([], $paperdle->getWarnings());

        $this->assertEquals($expected, $actual);
    }

    public function testGetDecoderTable(): void
    {
        $paperdle = new Paperdle();
        $paperdle->generate('CLAVE');
        $actual = $paperdle->getDecoderTableAsText();

        $expected = <<<TEXT
           A B C D E F G H I J K L M N O P Q R S T U V W X Y Z
           - - - - - - - - - - - - - - - - - - - - - - - - - -
        1: # # # # # ? # # # # # # # # # # ? # # # # # # # ? ?
        2: # = # # # # # ? # # # = # # # # # # # # # # # # # #
        3: # # # = # # # # ? # # # ? # # ? ? # = # # ? # # # #
        4: # ? ? # # # ? ? # # # # ? # # # # # # # # # ? # # #
        5: # ? # # # # ? # # # # # # # # # # # ? # # # # ? # =
        TEXT;

        $this->assertEquals([], $paperdle->getErrors());
        $this->assertEquals([], $paperdle->getWarnings());

        $this->assertEquals($expected, $actual);
    }

    public function testGetDecoderTableNonEnglish(): void
    {
        $paperdle = new Paperdle();
        $paperdle->generate('PEÑAS', 'ABCDEFGHIJKLMNÑOPQRSTUVWXYZ');
        $actual = $paperdle->getDecoderTableAsText();

        $expected = <<<TEXT
           A B C D E F G H I J K L M N Ñ O P Q R S T U V W X Y Z
           - - - - - - - - - - - - - - - - - - - - - - - - - - -
        1: # # # ? ? # # # # # # ? # # # # # # # # # # ? # # # ?
        2: ? = # # # # # # # # # # # # # ? # ? # ? ? # ? # ? # =
        3: # # ? # # # # # # ? ? # # # # = ? # # # # # # # # # ?
        4: = # # # # # # # # # # # # ? # # # # = # # ? # ? # # #
        5: # # # # # # # # # # # # # # # # # # # # # # # # # # #
        TEXT;

        $this->assertEquals([], $paperdle->getErrors());
        $this->assertEquals([], $paperdle->getWarnings());

        $this->assertEquals($expected, $actual);
    }

    public function testGetEncodedSolution()
    {
        $paperdle = new Paperdle();
        $paperdle->generate('CLAVE');
        $encodedSolution = $paperdle->getEncodedSolution();

        $this->assertEquals('M4P5B3C2W1', $encodedSolution);
    }

    public function testGetEncodedSolutionNonEnglish()
    {
        $paperdle = new Paperdle();
        $paperdle->generate('PEÑAS', 'ABCDEFGHIJKLMNÑOPQRSTUVWXYZ');
        $encodedSolution = $paperdle->getEncodedSolution();

        $this->assertEquals('Q4V1S1D1P5', $encodedSolution);
    }

    public function testGetSolutionDecoderTable(): void
    {
        $paperdle = new Paperdle();
        $paperdle->generate('CLAVE');
        $actual = $paperdle->getSolutionDecoderTableAsText();

        $expected = <<<TEXT
           A B C D E F G H I J K L M N O P Q R S T U V W X Y Z
           - - - - - - - - - - - - - - - - - - - - - - - - - -
        1: J H L I U R I Q C W N X V X B C S N N X O O E H M E
        2: Z G V O I L D Q C Y E B I W B F L P G L M M B Q M B
        3: F A T X P Y O M X E O R U N D E P R N B B U H E N Y
        4: U F N V R I U Y F O Q H C Z C W U Q L O D L P M U U
        5: Q X S U P U D P G I U R G U Z L V G A T U N Q E C O
        TEXT;

        $this->assertEquals([], $paperdle->getErrors());
        $this->assertEquals([], $paperdle->getWarnings());

        $this->assertEquals($expected, $actual);
    }

    public function testGetSolutionDecoderTableNonEnglish(): void
    {
        $paperdle = new Paperdle();
        $paperdle->generate('PEÑAS', 'ABCDEFGHIJKLMNÑOPQRSTUVWXYZ');
        $actual = $paperdle->getSolutionDecoderTableAsText();

        $expected = <<<TEXT
           A B C D E F G H I J K L M N Ñ O P Q R S T U V W X Y Z
           - - - - - - - - - - - - - - - - - - - - - - - - - - -
        1: G A I A Y U V O F Z H I I E L C M I N Ñ G T E L G F Ñ
        2: W C Z G V T P W I I B D S A Z B M J D I J K S B R U Q
        3: O V D R Z Q K S H J O Q N G C G X O G X Ñ V S D Z I B
        4: F O Q I Z Y E C T E S X C Q Q N S P A Q P R L V I T O
        5: H F S E I N V O R R B P B U M X S Q N G H F H S G P D
        TEXT;

        $this->assertEquals([], $paperdle->getErrors());
        $this->assertEquals([], $paperdle->getWarnings());

        $this->assertEquals($expected, $actual);
    }


    public function testGetErrors(): void
    {
        $paperdle = new Paperdle();
        $paperdle->generate('BAD');
        $errors = $paperdle->getErrors();

        $this->assertIsArray($errors);
        $this->assertNotEmpty($errors);
    }

    public function testGetWarnings(): void
    {
        $paperdle = new Paperdle();
        $warnings = $paperdle->getWarnings();

        $this->assertIsArray($warnings);
        $this->assertEmpty($warnings);
    }
}
