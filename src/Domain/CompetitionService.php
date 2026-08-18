<?php
declare(strict_types=1);

final class CompetitionService
{
    /** @return array{groups:array<int,array{code:string,members:array<int,string>}>,fixtures:array<int,array{group_code:string,player_a:string,player_b:string}>} */
    public function generateGroups(array $approvedRegistrationIds, int $groupCount = 8, int $groupSize = 4, ?int $seed = null): array
    {
        $expected = $groupCount * $groupSize;
        if (count($approvedRegistrationIds) !== $expected) throw new InvalidArgumentException('จำนวนผู้เล่นที่อนุมัติต้องเท่ากับ ' . $expected . ' คน');
        if ($seed !== null) mt_srand($seed);
        $players = array_values($approvedRegistrationIds); shuffle($players);
        $groups = []; $fixtures = [];
        for ($group = 0; $group < $groupCount; $group++) {
            $code = chr(65 + $group); $members = array_slice($players, $group * $groupSize, $groupSize); $groups[] = ['code' => $code, 'members' => $members];
            for ($i = 0; $i < count($members); $i++) for ($j = $i + 1; $j < count($members); $j++) $fixtures[] = ['group_code' => $code, 'player_a' => $members[$i], 'player_b' => $members[$j]];
        }
        return ['groups' => $groups, 'fixtures' => $fixtures];
    }
}
