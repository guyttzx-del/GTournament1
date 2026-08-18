<?php
declare(strict_types=1);

final class RankingService
{
    /** @param array<int,array{registration_id_a:string,registration_id_b:string,score_a:int,score_b:int}> $results */
    public function calculate(array $results): array
    {
        $table = [];
        foreach ($results as $result) {
            $a = (string) $result['registration_id_a'];
            $b = (string) $result['registration_id_b'];
            foreach ([$a, $b] as $player) {
                if (!isset($table[$player])) $table[$player] = ['registration_id' => $player, 'played' => 0, 'wins' => 0, 'draws' => 0, 'losses' => 0, 'goals_for' => 0, 'goals_against' => 0, 'points' => 0];
            }
            $scoreA = max(0, (int) $result['score_a']); $scoreB = max(0, (int) $result['score_b']);
            $table[$a]['played']++; $table[$b]['played']++;
            $table[$a]['goals_for'] += $scoreA; $table[$a]['goals_against'] += $scoreB;
            $table[$b]['goals_for'] += $scoreB; $table[$b]['goals_against'] += $scoreA;
            if ($scoreA > $scoreB) { $table[$a]['wins']++; $table[$a]['points'] += 3; $table[$b]['losses']++; }
            elseif ($scoreB > $scoreA) { $table[$b]['wins']++; $table[$b]['points'] += 3; $table[$a]['losses']++; }
            else { $table[$a]['draws']++; $table[$b]['draws']++; $table[$a]['points']++; $table[$b]['points']++; }
        }
        foreach ($table as &$row) $row['goal_difference'] = $row['goals_for'] - $row['goals_against'];
        unset($row);
        usort($table, static function (array $left, array $right): int {
            foreach (['points', 'goal_difference', 'goals_for'] as $field) if ($left[$field] !== $right[$field]) return $right[$field] <=> $left[$field];
            return strcmp($left['registration_id'], $right['registration_id']);
        });
        foreach ($table as $index => &$row) $row['rank'] = $index + 1;
        return $table;
    }
}
