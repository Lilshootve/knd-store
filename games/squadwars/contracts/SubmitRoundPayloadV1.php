<?php
declare(strict_types=1);

final class SubmitRoundPayloadV1
{
    public string $battleToken;
    public int $round;
    /** @var array<int, array<string, mixed>> */
    public array $actions;
    public int $clientStateVersion;

    /**
     * @param array<int, array<string, mixed>> $actions
     */
    public function __construct(string $battleToken, int $round, array $actions, int $clientStateVersion)
    {
        $this->battleToken = $battleToken;
        $this->round = $round;
        $this->actions = $actions;
        $this->clientStateVersion = $clientStateVersion;
    }
}
