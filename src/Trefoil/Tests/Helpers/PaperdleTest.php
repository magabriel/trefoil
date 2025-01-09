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
        $this->assertCount(26, $evaluationTable); // 26 letters in the alphabet
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
        $evaluationTable = $paperdle->getEvaluationTable();

        $expected = [
            'A' => '??=??',
            'B' => '#####',
            'C' => '=????',
            'D' => '#####',
            'E' => '????=',
            'F' => '#####',
            'G' => '#####',
            'H' => '#####',
            'I' => '#####',
            'J' => '#####',
            'K' => '#####',
            'L' => '?=???',
            'M' => '#####',
            'N' => '#####',
            'O' => '#####',
            'P' => '#####',
            'Q' => '#####',
            'R' => '#####',
            'S' => '#####',
            'T' => '#####',
            'U' => '#####',
            'V' => '???=?',
            'W' => '#####',
            'X' => '#####',
            'Y' => '#####',
            'Z' => '#####',
        ];

        // Make the actual array more readable
        $actual = array_map(
            fn($positions) => implode('', $positions),
            $evaluationTable
        );

        $this->assertEquals([], $paperdle->getErrors());
        $this->assertEquals([], $paperdle->getWarnings());

        $this->assertEquals($expected, $actual);
    }

    public function testGetEvaluationTableNonEnglish(): void
    {
        $paperdle = new Paperdle();
        $paperdle->generate('PEÑAS', 'ABCDEFGHIJKLMNÑOPQRSTUVWXYZ');
        $evaluationTable = $paperdle->getEvaluationTable();

        $expected = [
            'A' => '???=?',
            'B' => '#####',
            'C' => '#####',
            'D' => '#####',
            'E' => '?=???',
            'F' => '#####',
            'G' => '#####',
            'H' => '#####',
            'I' => '#####',
            'J' => '#####',
            'K' => '#####',
            'L' => '#####',
            'M' => '#####',
            'N' => '#####',
            'Ñ' => '??=??',
            'O' => '#####',
            'P' => '=????',
            'Q' => '#####',
            'R' => '#####',
            'S' => '????=',
            'T' => '#####',
            'U' => '#####',
            'V' => '#####',
            'W' => '#####',
            'X' => '#####',
            'Y' => '#####',
            'Z' => '#####',
        ];

        // Make the actual array more readable
        $actual = array_map(
            fn($positions) => implode('', $positions),
            $evaluationTable
        );

        $this->assertEquals([], $paperdle->getErrors());
        $this->assertEquals([], $paperdle->getWarnings());

        $this->assertEquals($expected, $actual);
    }

    public function testGeRandomizerTable(): void
    {
        $paperdle = new Paperdle();
        $paperdle->generate('CLAVE');
        $randomizerTable = $paperdle->getRandomizerTable();

        // NOTE: Expected values are fixed because the randomizer is seeded after the hidden word.
        $expected = [
            'A' => 'M5J3F3B5Q5',
            'B' => 'T1J1R3C1C3',
            'C' => 'E3F1A2O5T2',
            'D' => 'P3F5Y2W5O3',
            'E' => 'F4Z3S2N5D1',
            'F' => 'J5V1B2U1H5',
            'G' => 'G4E4I1N2W2',
            'H' => 'B4Q3L1L2R5',
            'I' => 'W3C5L5V3I5',
            'J' => 'Z4S4N4D4Z2',
            'K' => 'F2U2Z5L4Q1',
            'L' => 'K1W1M3A3I4',
            'M' => 'L3A5A4M1R4',
            'N' => 'X5M4S3A1P4',
            'O' => 'H2P2X3S1G1',
            'P' => 'I3S5N1D2Q2',
            'Q' => 'N3V2P5G5Y1',
            'R' => 'B3K4T4K2R1',
            'S' => 'Y5D3T5X2J2',
            'T' => 'Y4X1D5Z1H3',
            'U' => 'E1W4M2T3E5',
            'V' => 'X4B1O2O1G2',
            'W' => 'U3H1U4C2H4',
            'X' => 'O4V4P1K3R2',
            'Y' => 'C4E2Q4G3K5',
            'Z' => 'Y3V5J4U5I2',
        ];

        // Make the actual array more readable
        $actual = array_map(
            fn($positions) => implode('', $positions),
            $randomizerTable
        );

        $this->assertEquals([], $paperdle->getErrors());
        $this->assertEquals([], $paperdle->getWarnings());

        $this->assertEquals($expected, $actual);
    }

    public function testGeRandomizerTableNonEnglish(): void
    {
        $paperdle = new Paperdle();
        $paperdle->generate('PEÑAS', 'ABCDEFGHIJKLMNÑOPQRSTUVWXYZ');
        $randomizerTable = $paperdle->getRandomizerTable();

        // NOTE: Expected values are fixed because the randomizer is seeded after the hidden word.
        $expected = [
            'A' => 'I5Y2T1B2J2',
            'B' => 'D2Ñ5O2W4V5',
            'C' => 'A1A2H5Z2T3',
            'D' => 'M1N5O1D1I3',
            'E' => 'W2G3G5W5P4',
            'F' => 'R4Q1J3H3Y4',
            'G' => 'K5F4L5O5K1',
            'H' => 'N3D4Q3T4F5',
            'I' => 'B4T5X2F2C5',
            'J' => 'X3L2S3H2U4',
            'K' => 'X1W3R3Q5M4',
            'L' => 'B5Y3P3P1Z5',
            'M' => 'B3S2V2J1P5',
            'N' => 'J4Q2E4P2W1',
            'Ñ' => 'K2A3S4F3R1',
            'O' => 'C3G2U1Y5F1',
            'P' => 'G1S5Z1S1K3',
            'Q' => 'Ñ1X5C1V4M2',
            'R' => 'Y1V1U5N4M3',
            'S' => 'A5T2A4N1Q4',
            'T' => 'E3L1E5Z4Ñ3',
            'U' => 'Ñ4N2V3M5Z3',
            'V' => 'L3H4R5X4D5',
            'W' => 'C4I2G4D3R2',
            'X' => 'L4O4C2O3J5',
            'Y' => 'U3I1K4H1E2',
            'Z' => 'U2I4Ñ2E1B1',
        ];

        // Make the actual array more readable
        $actual = array_map(
            fn($positions) => implode('', $positions),
            $randomizerTable
        );

        $this->assertEquals([], $paperdle->getErrors());
        $this->assertEquals([], $paperdle->getWarnings());

        $this->assertEquals($expected, $actual);
    }

    public function testGetDecoderTable(): void
    {
        $paperdle = new Paperdle();
        $paperdle->generate('CLAVE');
        $decoderTable = $paperdle->getDecoderTable();

        $expected = [
            'A' => '#??##',
            'B' => '?###?',
            'C' => '#####',
            'D' => '=####',
            'E' => '##=##',
            'F' => '?#=?#',
            'G' => '#?###',
            'H' => '#####',
            'I' => '###?#',
            'J' => '##?##',
            'K' => '?####',
            'L' => '#####',
            'M' => '##?#?',
            'N' => '####?',
            'O' => '=?##?',
            'P' => '#####',
            'Q' => '####?',
            'R' => '#####',
            'S' => '#?###',
            'T' => '#?###',
            'U' => '#####',
            'V' => '#####',
            'W' => '=####',
            'X' => '###?#',
            'Y' => '#####',
            'Z' => '##?##',

        ];

        // Make the actual array more readable
        $actual = array_map(
            fn($positions) => implode('', $positions),
            $decoderTable
        );

        $this->assertEquals([], $paperdle->getErrors());
        $this->assertEquals([], $paperdle->getWarnings());

        $this->assertEquals($expected, $actual);
    }

    public function testGetDecoderTableNonEnglish(): void
    {
        $paperdle = new Paperdle();
        $paperdle->generate('PEÑAS', 'ABCDEFGHIJKLMNÑOPQRSTUVWXYZ');
        $decoderTable = $paperdle->getDecoderTable();

        $expected = [
            'A' => '##???',
            'B' => '#=###',
            'C' => '#####',
            'D' => '#####',
            'E' => '#####',
            'F' => '##?##',
            'G' => '=#=#?',
            'H' => '#####',
            'I' => '####?',
            'J' => '#?###',
            'K' => '#??##',
            'L' => '#####',
            'M' => '#####',
            'N' => '?####',
            'Ñ' => '#####',
            'O' => '#####',
            'P' => '###?#',
            'Q' => '###=#',
            'R' => '?####',
            'S' => '?##=?',
            'T' => '??###',
            'U' => '#####',
            'V' => '#####',
            'W' => '#?##?',
            'X' => '#####',
            'Y' => '#?###',
            'Z' => '?####',

        ];

        // Make the actual array more readable
        $actual = array_map(
            fn($positions) => implode('', $positions),
            $decoderTable
        );

        $this->assertEquals([], $paperdle->getErrors());
        $this->assertEquals([], $paperdle->getWarnings());

        $this->assertEquals($expected, $actual);
    }

    public function testGetEncodedSolution()
    {
        $paperdle = new Paperdle();
        $paperdle->generate('CLAVE');
        $encodedSolution = $paperdle->getEncodedSolution();

        $this->assertEquals('M4-P5-B3-C2-W1', $encodedSolution);
    }

    public function testGetEncodedSolutionNonEnglish()
    {
        $paperdle = new Paperdle();
        $paperdle->generate('PEÑAS', 'ABCDEFGHIJKLMNÑOPQRSTUVWXYZ');
        $encodedSolution = $paperdle->getEncodedSolution();

        $this->assertEquals('P4-U1-R1-D1-O5', $encodedSolution);
    }

    public function testGetSolutionDecoderTable(): void
    {
        $paperdle = new Paperdle();
        $paperdle->generate('CLAVE');
        $decoderTable = $paperdle->getSolutionDecoderTable();

        $expected = [
            'A' => 'JHLIU',
            'B' => 'RIAQC',
            'C' => 'WVNXV',
            'D' => 'XBCSN',
            'E' => 'NXOOH',
            'F' => 'MEZGO',
            'G' => 'ILDQC',
            'H' => 'YEBIW',
            'I' => 'BFLPG',
            'J' => 'LMMBQ',
            'K' => 'MBFTX',
            'L' => 'PYOMX',
            'M' => 'EORCU',
            'N' => 'NDEPR',
            'O' => 'NBBUH',
            'P' => 'ENYUL',
            'Q' => 'FNVRI',
            'R' => 'UYFOQ',
            'S' => 'HZCWU',
            'T' => 'QLODL',
            'U' => 'PMUUQ',
            'V' => 'XSUPU',
            'W' => 'EDPGI',
            'X' => 'URGUZ',
            'Y' => 'VGATU',
            'Z' => 'NQECO',
        ];

        // Make the actual array more readable
        $actual = array_map(
            fn($positions) => implode('', $positions),
            $decoderTable
        );

        $this->assertEquals([], $paperdle->getErrors());
        $this->assertEquals([], $paperdle->getWarnings());

        $this->assertEquals($expected, $actual);
    }

    public function testGetSolutionDecoderTableNonEnglish(): void
    {
        $paperdle = new Paperdle();
        $paperdle->generate('PEÑAS', 'ABCDEFGHIJKLMNÑOPQRSTUVWXYZ');
        $decoderTable = $paperdle->getSolutionDecoderTable();

        $expected = [
            'A' => 'GAIYU',
            'B' => 'VOFZH',
            'C' => 'IIELC',
            'D' => 'AMING',
            'E' => 'TLGFÑ',
            'F' => 'WCZGV',
            'G' => 'TPWII',
            'H' => 'BDSAZ',
            'I' => 'BMJDI',
            'J' => 'JKSBR',
            'K' => 'UQOVD',
            'L' => 'RZQKS',
            'M' => 'HJOQN',
            'N' => 'GCGXO',
            'Ñ' => 'GXÑVS',
            'O' => 'DZIBS',
            'P' => 'FOQPI',
            'Q' => 'ZYECT',
            'R' => 'ÑESXC',
            'S' => 'QQNSA',
            'T' => 'QPRLV',
            'U' => 'EITOH',
            'V' => 'FSEIN',
            'W' => 'VORRB',
            'X' => 'PBUMX',
            'Y' => 'QNGHF',
            'Z' => 'HSGPD',
        ];

        // Make the actual array more readable
        $actual = array_map(
            fn($positions) => implode('', $positions),
            $decoderTable
        );

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
