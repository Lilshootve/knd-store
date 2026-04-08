<?php
declare(strict_types=1);

final class SubmitRoundResultV1
{
    /** @var list<array<string, mixed>> */
    public array $events;
    /** @var list<string> */
    public array $timelineUsed;
    /** Ronda que acaba de resolverse (no confundir con state.round tras planning). */
    public int $round;
    public bool $battleOver;
    public ?string $winner;
    public int $stateVersion;
    public string $nextPhase;

    /**
     * @param list<array<string, mixed>> $events
     * @param list<string> $timelineUsed
     */
    public function __construct(
        array $events,
        array $timelineUsed,
        int $round,
        bool $battleOver,
        ?string $winner,
        int $stateVersion,
        string $nextPhase
    ) {
        $this->events = $events;
        $this->timelineUsed = $timelineUsed;
        $this->round = $round;
        $this->battleOver = $battleOver;
        $this->winner = $winner;
        $this->stateVersion = $stateVersion;
        $this->nextPhase = $nextPhase;
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        return [
            'events' => $this->events,
            'timelineUsed' => $this->timelineUsed,
            'round' => $this->round,
            'battleOver' => $this->battleOver,
            'winner' => $this->winner,
            'stateVersion' => $this->stateVersion,
            'nextPhase' => $this->nextPhase,
        ];
    }

    public function withStateVersion(int $version): self
    {
        return new self(
            $this->events,
            $this->timelineUsed,
            $this->round,
            $this->battleOver,
            $this->winner,
            $version,
            $this->nextPhase,
        );
    }
}
