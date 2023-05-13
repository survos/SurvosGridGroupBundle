<?php

declare(strict_types=1);

namespace Survos\GridGroupBundle\Model;

class GridGroup
{

    public function __construct(
        private ?string $code=null,
        private ?string $dir=null,
        private string $startString = '!---',
        private string $endString = '---!',
        private array $grids = []
    )
    {
        // better would be something that implements ArrayAccess...
    }

    /**
     * @return string|null
     */
    public function getCode(): ?string
    {
        return $this->code;
    }

    /**
     * @param string|null $code
     * @return GridGroup
     */
    public function setCode(?string $code): GridGroup
    {
        $this->code = $code;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getDir(): ?string
    {
        return $this->dir;
    }

    /**
     * @param string|null $dir
     * @return GridGroup
     */
    public function setDir(?string $dir): GridGroup
    {
        $this->dir = $dir;
        return $this;
    }

    public function addGrid(Grid $grid): self
    {
        $this->grids[$grid->getKey()] = $grid;
        return $this;
    }

    /**
     * @return <int,Grid>
     */
    public function getGrids(): array
    {
        return $this->grids;
    }

    public function getGridKeys(): array
    {
        return array_keys($this->grids);
    }

    public function getGrid(string $key): Grid
    {
        return $this->grids[$key];
    }

    public function getGridCount(): int
    {
        return count($this->getGrids());
    }

    // return csv data from the GROUP
    public function loadString(string $groupText, bool $asArray = false): self
    {
        $mm = [];
        $ok = preg_match_all('/!-{3}(.*?).csv(.*?)-{3}!/ms', $groupText, $mm, PREG_SET_ORDER);
        assert($ok, $groupText);

        foreach ($mm as $m) {
            [$all, $key, $csv] = $m;
            $csv = trim($csv);
//            $result[$key] = $csv;
            $grid = (new Grid($key))
                ->loadString($csv);
            $this->addGrid($grid);
        }
        return $this;
    }

    public function asTextString(int $limit = 0)
    {
//        $x = '';
//        foreach($this->getGrids() as $grid) {
//            $x .= sprintf("%s\n%s.csv\n%s\n\n", $this->startString, $grid->getKey(), $this->endString);
//        }

        return join("\n\n", array_map(
            fn(Grid $grid) => sprintf("%s\n%s.csv\n%s\n%s\n%s", $this->startString,
                $grid->getKey(),
                $grid->getHeadersAsCsvString(),
                $grid->getDataAsString(limit: $limit),
                $this->endString
            ),
            $this->getGrids())
        );
    }

    public function getGridFilename(Grid $grid)
    {
        return rtrim($this->getDir(), '/') . '/' . $grid->getKey();
    }


}

