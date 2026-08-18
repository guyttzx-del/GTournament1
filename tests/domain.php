<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Domain/RankingService.php';
require_once __DIR__ . '/../src/Domain/RegistrationStatus.php';
require_once __DIR__ . '/../src/Domain/CompetitionService.php';

function assert_same($expected, $actual, string $message): void
{
    if ($expected !== $actual) throw new RuntimeException($message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')');
}

$ranking = (new RankingService())->calculate([
    ['registration_id_a' => 'a', 'registration_id_b' => 'b', 'score_a' => 2, 'score_b' => 0],
    ['registration_id_a' => 'a', 'registration_id_b' => 'c', 'score_a' => 2, 'score_b' => 0],
    ['registration_id_a' => 'b', 'registration_id_b' => 'c', 'score_a' => 0, 'score_b' => 3],
]);
assert_same('a', $ranking[0]['registration_id'], 'winner should rank first');
assert_same(6, $ranking[0]['points'], 'winner points should be 3+3');
assert_same(4, $ranking[0]['goal_difference'], 'winner goal difference should be calculated');
assert_same(true, RegistrationStatus::canTransition('pending_review', 'approved'), 'pending review should be approvable');
assert_same(false, RegistrationStatus::canTransition('approved', 'pending_review'), 'approved registration cannot go back to review');
assert_same('รอตรวจสอบ', RegistrationStatus::label('pending_review'), 'status label should be localized');
$ids = []; for ($i = 1; $i <= 32; $i++) $ids[] = 'r' . $i;
$competition = (new CompetitionService())->generateGroups($ids, 8, 4, 42);
assert_same(8, count($competition['groups']), 'competition should create 8 groups');
assert_same(48, count($competition['fixtures']), '8 groups of 4 should create 48 fixtures');
assert_same(4, count($competition['groups'][0]['members']), 'each group should contain 4 players');
echo "Domain tests passed\n";
