<?php
// Write an action using echo(). DON'T FORGET THE TRAILING \n
// To debug: error_log(var_export($var, true)); (equivalent to var_dump)

// $nextCheckpointX: x coordinates of the next check point
// $nextCheckpointY: y coordinates of the next check point
// $nextCheckpointDist: distance to the next checkpoint
// $nextCheckpointAngle: angle between your pod orientation and the direction of the next checkpoint

// You have to output the target coordinates
// followed by the power (0 <= thrust <= 100)
// i.e.: "x y thrust"
Game::start();

class Game
{
    public static function start(): void
    {
        $pod = new Pod();
        $oponent = new Pod();
        while (true) {
            $info = self::getNextInfo();

            $pod->updatePosition($info['podPosition']);
            $oponent->updatePosition($info['oponentPosition']);
            echo $pod->nextMove($info['checkpointPosition'], $info['checkpointAngle'], $oponent);
        }
    }

    private static function getNextInfo(): array
    {
        fscanf(
            STDIN,
            "%d %d %d %d %d %d",
            $x,
            $y,
            $nextCheckpointX,
            $nextCheckpointY,
            $nextCheckpointDist,
            $nextCheckpointAngle
        );
        fscanf(STDIN, "%d %d", $opponentX, $opponentY);

        return [
            'podPosition' => new Coordinate($x, $y),
            'oponentPosition' => new Coordinate($opponentX, $opponentY),
            'checkpointPosition' => new Coordinate($nextCheckpointX, $nextCheckpointY),
            'checkpointDistance' => $nextCheckpointDist,
            'checkpointAngle' => $nextCheckpointAngle,
        ];
    }
}

final class Pod
{
    private const MAX_THRUST = 100;

    /** @var Checkpoints */
    private $checkpoints;

    /** @var Coordinate */
    private $currentPosition;

    /** @var Coordinate */
    private $previousPosition;

    /** @var bool */
    private $canBoost = true;

    public function __construct()
    {
        $this->checkpoints = new Checkpoints();
    }

    public function updatePosition(Coordinate $position): void
    {
        $this->previousPosition = $this->currentPosition;
        $this->currentPosition = $position;
    }

    public function nextMove(Coordinate $checkpointPosition, int $checkpointAngle, self $oponent): string
    {
        error_log(var_export($this->checkpoints->update($checkpointPosition), true));
        $checkpointAngle = abs($checkpointAngle);

        $target = $checkpointPosition;
        $useBoost = false;
        error_log(var_export($checkpointAngle, true));
        $distance = $this->currentPosition->distanceTo($checkpointPosition);
        $checkpointPosition = $this->checkpoints->getNextCheckpoint($this)->getCoordinate();

        if ($checkpointAngle < 2) {
            error_log(var_export($this->canBoost, true));
            error_log(var_export($this->checkpoints->canBoost(), true));
            $useBoost = $this->canBoost && $this->checkpoints->canBoost();
            $thrust = self::MAX_THRUST;
        } else {
            $possitionDiff = $this->currentPosition->subtract($this->previousPosition);
            $target = $checkpointPosition->subtract($possitionDiff->multiplyByFactor(3));
            $distanceSlowdownFactor = max(
                0.1,
                min(
                    1,
                    $distance / (Checkpoint::RADIUS * 4)
                )
            );
            $angleSlowdownFactor = 1 - max(0.1, min(1, $checkpointAngle/90));
            $thrust = (int) (self::MAX_THRUST * round($distanceSlowdownFactor, 1) * round($angleSlowdownFactor, 1));
        }

        if ($this->getCurrentPosition()->distanceTo($oponent->getCurrentPosition()) < 410) {
            $thrust = 'SHIELD';
        } elseif ($useBoost) {
            $thrust = 'BOOST';
        }

        return $target . " " . $thrust . "\n";
    }

    public function getCurrentPosition(): Coordinate
    {
        return $this->currentPosition;
    }
}

final class Coordinate
{
    private const STRING_SEPARATOR = ' ';

    /** @var int */
    private $x;

    /** @var int */
    private $y;

    public function __construct(int $x, int $y)
    {
        $this->x = $x;
        $this->y = $y;
    }

    public function getX(): int
    {
        return $this->x;
    }

    public function getY(): int
    {
        return $this->y;
    }

    public function equals(?self $coordinate): bool
    {
        if ($coordinate === null) {
            return false;
        }

        return $this->x === $coordinate->x && $this->y === $coordinate->y;
    }

    public function add(?self $coordinate): self
    {
        if ($coordinate === null) {
            return $this;
        }

        return new self(
            $this->x + $coordinate->x,
            $this->y + $coordinate->y
        );
    }

    public function subtract(?self $coordinate): self
    {
        if ($coordinate === null) {
            return $this;
        }

        return new self(
            $this->x - $coordinate->x,
            $this->y - $coordinate->y
        );
    }

    public function multiplyByFactor(float $factor): self
    {
        return new self(
            (int) $this->x * $factor,
            (int) $this->y * $factor
        );
    }

    public function distanceTo(?self $coordinate): ?float
    {
        if ($coordinate === null) {
            return null;
        }

        $dX = $this->x - $coordinate->x;
        $dY = $this->y - $coordinate->y;

        return sqrt($dX * $dX + $dY * $dY);
    }

    public static function fromString(string $coordinate): self
    {

        [$x, $y] = explode(self::STRING_SEPARATOR, $coordinate);

        return new self($x, $y);
    }

    public function asString(): string
    {
        return $this->x . self::STRING_SEPARATOR . $this->y;
    }

    public function __toString(): string
    {
        return $this->asString();
    }
}

final class Checkpoint
{
    public const RADIUS = 600;

    /** @var int */
    private $index;

    /** @var Coordinate */
    private $coordinate;

    public function __construct(int $index, Coordinate $coordinate)
    {
        $this->index = $index;
        $this->coordinate = $coordinate;
    }

    public function getIndex(): int
    {
        return $this->index;
    }

    public function getCoordinate(): Coordinate
    {
        return $this->coordinate;
    }

    public function asString(): string
    {
        return $this->coordinate;
    }

    public function __toString(): string
    {
        return $this->asString();
    }

    public function distanceTo(?self $checkpoint): ?float
    {
        if ($checkpoint === null) {
            return null;
        }

        return $this->coordinate->distanceTo($checkpoint->coordinate);
    }

    public function equals(?self $checkpoint): bool
    {
        if ($checkpoint === null) {
            return false;
        }

        return $this->coordinate->equals($checkpoint->coordinate);
    }

    public function isAtCoordinate(?Coordinate $coordinate): bool
    {
        return $this->coordinate->equals($coordinate);
    }
}

final class Checkpoints
{
    /** @var Checkpoint[] */
    private static $checkpoints = [];

    /** @var int */
    private $lap = 1;

    /** @var Checkpoint|null */
    private $nextCheckpoint;

    /** @var Checkpoint|null */
    private static $longestDistanceCheckpoint;

    public function update(Coordinate $coordinate): string
    {
        $key = $coordinate->asString();

        if (!array_key_exists($key, self::$checkpoints)) {
            $this->nextCheckpoint = self::$checkpoints[$key] = new Checkpoint(count(self::$checkpoints) + 1, $coordinate);
        } elseif ($this->nextCheckpoint !== null && !$this->nextCheckpoint->isAtCoordinate($coordinate)) {
            $this->nextCheckpoint = self::$checkpoints[$key];
            if (array_key_first(self::$checkpoints) === $key) {
                if (self::$longestDistanceCheckpoint === null && $this->lap === 1) {
                    self::calculateLongestDistanceCheckpoint();
                }
                ++$this->lap;
            }
        }

        return sprintf(
            'next checkpoint: %d/%s | lap: %d',
            self::$checkpoints[$key]->getIndex(),
            $this->lap !== 1 ? count(self::$checkpoints) : '?',
            $this->lap
        );
    }

    private static function calculateLongestDistanceCheckpoint(): void
    {
        error_log(var_export('calculate', true));

        /** @var Checkpoint[] $checkpoints */
        $checkpoints = array_values(self::$checkpoints);
        $longestDistance = null;
        foreach ($checkpoints as $index => $checkpoint) {
            $distance = $checkpoint->distanceTo($checkpoints[$index - 1] ?? $checkpoints[count($checkpoints) - 1]);
            if ($distance > $longestDistance) {
                $longestDistance = $distance;
                self::$longestDistanceCheckpoint = $checkpoint;
            }
        }
        error_log(var_export(self::$longestDistanceCheckpoint, true));
    }

    public function getNextCheckpoint(Pod $pod): Checkpoint
    {
        if ($pod->getCurrentPosition()->distanceTo($this->nextCheckpoint->getCoordinate()) > 1000) {
            return $this->nextCheckpoint;
        }

        /** @var Checkpoint[] $checkpoints */
        $checkpoints = array_values(self::$checkpoints);
        $index = array_search($this->nextCheckpoint, $checkpoints);

        return $checkpoints[$index + 1] ?? ($this->lap === 1 ? $this->nextCheckpoint : $checkpoints[0]);
    }

    /** @return Checkpoint[] */
    public function getCheckpoints(): array
    {
        return self::$checkpoints;
    }

    public function getLap(): int
    {
        return $this->lap;
    }

    public function canBoost(): bool
    {
        return self::$longestDistanceCheckpoint !== null && $this->nextCheckpoint->equals(self::$longestDistanceCheckpoint);
    }
}
