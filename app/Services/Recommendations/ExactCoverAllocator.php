<?php

declare(strict_types=1);

namespace App\Services\Recommendations;

final class ExactCoverAllocator
{
    private const DEFAULT_MAX_OPTIONS = 256;

    private const DEFAULT_MAX_SEARCH_NODES = 10000;

    /**
     * Solve a deterministic exact-cover allocation for batch/recurring workflows.
     *
     * The quick-booking recommendation endpoint should keep `weighted_v1` as its
     * default. This core is for later workflows where every required operational
     * constraint must be covered exactly once.
     *
     * @param  array<int, string>  $requiredConstraints
     * @param  array<int, array{id: string, covers: array<int, string>, weight?: int}>  $options
     * @return array{strategy: string, status: string, selected_option_ids: array<int, string>, covered_constraints: array<int, string>, search_nodes: int}
     */
    public function solve(
        array $requiredConstraints,
        array $options,
        int $maxOptions = self::DEFAULT_MAX_OPTIONS,
        int $maxSearchNodes = self::DEFAULT_MAX_SEARCH_NODES
    ): array {
        $required = self::normalizeConstraints($requiredConstraints);
        if ($required === []) {
            return $this->solved([], [], 0);
        }

        if (count($options) > $maxOptions) {
            return $this->fallback('fallback_input_limited', 0);
        }

        $normalizedOptions = $this->normalizeOptions($options, $required);
        $state = [
            'nodes' => 0,
            'max_nodes' => $maxSearchNodes,
            'limited' => false,
        ];
        $solution = $this->search($required, $required, $normalizedOptions, [], $state);

        if ($solution !== null) {
            $selectedIds = array_map(
                static fn (int $idx): string => $normalizedOptions[$idx]['id'],
                $solution
            );
            sort($selectedIds, SORT_STRING);

            return $this->solved($selectedIds, array_values($required), $state['nodes']);
        }

        return $this->fallback(
            $state['limited'] ? 'fallback_search_limited' : 'fallback_no_solution',
            $state['nodes']
        );
    }

    /**
     * @param  array<int, string>  $constraints
     * @return array<int, string>
     */
    public static function normalizeConstraints(array $constraints): array
    {
        $normalized = [];
        foreach ($constraints as $constraint) {
            $value = trim((string) $constraint);
            if ($value !== '') {
                $normalized[$value] = true;
            }
        }

        $values = array_keys($normalized);
        sort($values, SORT_STRING);

        return $values;
    }

    /**
     * @param  array<int, array{id: string, covers: array<int, string>, weight?: int}>  $options
     * @param  array<int, string>  $required
     * @return array<int, array{id: string, covers: array<int, string>, weight: int}>
     */
    private function normalizeOptions(array $options, array $required): array
    {
        $requiredLookup = array_fill_keys($required, true);
        $normalized = [];

        foreach ($options as $option) {
            $id = trim((string) $option['id']);
            $covers = [];
            foreach (self::normalizeConstraints((array) $option['covers']) as $constraint) {
                if (isset($requiredLookup[$constraint])) {
                    $covers[$constraint] = true;
                }
            }

            if ($id !== '' && $covers !== []) {
                $normalized[] = [
                    'id' => $id,
                    'covers' => array_keys($covers),
                    'weight' => (int) ($option['weight'] ?? 0),
                ];
            }
        }

        usort(
            $normalized,
            static fn (array $left, array $right): int => ($right['weight'] <=> $left['weight']) ?: strcmp($left['id'], $right['id'])
        );

        return $normalized;
    }

    /**
     * @param  array<int, string>  $required
     * @param  array<int, string>  $uncovered
     * @param  array<int, array{id: string, covers: array<int, string>, weight: int}>  $options
     * @param  array<int, int>  $selected
     * @param  array{nodes: int, max_nodes: int, limited: bool}  $state
     * @return ?array<int, int>
     */
    private function search(array $required, array $uncovered, array $options, array $selected, array &$state): ?array
    {
        if ($state['nodes'] >= $state['max_nodes']) {
            $state['limited'] = true;

            return null;
        }
        $state['nodes']++;

        if ($uncovered === []) {
            return $selected;
        }

        $covered = array_values(array_diff($required, $uncovered));
        $choice = $this->chooseNextConstraint($uncovered, $covered, $options);
        if ($choice === null || $choice['candidates'] === []) {
            return null;
        }

        foreach ($choice['candidates'] as $optionIndex) {
            $option = $options[$optionIndex];
            if (! in_array($choice['constraint'], $option['covers'], true)) {
                continue;
            }
            if (array_intersect($option['covers'], $covered) !== []) {
                continue;
            }

            $nextSelected = [...$selected, $optionIndex];
            $nextUncovered = array_values(array_diff($uncovered, $option['covers']));
            $solution = $this->search($required, $nextUncovered, $options, $nextSelected, $state);
            if ($solution !== null) {
                return $solution;
            }
            if ($state['limited']) {
                return null;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $uncovered
     * @param  array<int, string>  $covered
     * @param  array<int, array{id: string, covers: array<int, string>, weight: int}>  $options
     * @return ?array{constraint: string, candidates: array<int, int>}
     */
    private function chooseNextConstraint(array $uncovered, array $covered, array $options): ?array
    {
        $best = null;
        foreach ($uncovered as $constraint) {
            $candidates = [];
            foreach ($options as $idx => $option) {
                if (
                    in_array($constraint, $option['covers'], true)
                    && array_intersect($option['covers'], $covered) === []
                ) {
                    $candidates[] = $idx;
                }
            }

            if (
                $best === null
                || count($candidates) < count($best['candidates'])
                || (count($candidates) === count($best['candidates']) && strcmp($constraint, $best['constraint']) < 0)
            ) {
                $best = [
                    'constraint' => $constraint,
                    'candidates' => $candidates,
                ];
            }
        }

        return $best;
    }

    /**
     * @param  array<int, string>  $selectedIds
     * @param  array<int, string>  $coveredConstraints
     * @return array{strategy: string, status: string, selected_option_ids: array<int, string>, covered_constraints: array<int, string>, search_nodes: int}
     */
    private function solved(array $selectedIds, array $coveredConstraints, int $searchNodes): array
    {
        return [
            'strategy' => 'exact_cover_v1',
            'status' => 'solved',
            'selected_option_ids' => $selectedIds,
            'covered_constraints' => $coveredConstraints,
            'search_nodes' => $searchNodes,
        ];
    }

    /**
     * @return array{strategy: string, status: string, selected_option_ids: array<int, string>, covered_constraints: array<int, string>, search_nodes: int}
     */
    private function fallback(string $status, int $searchNodes): array
    {
        return [
            'strategy' => 'exact_cover_v1',
            'status' => $status,
            'selected_option_ids' => [],
            'covered_constraints' => [],
            'search_nodes' => $searchNodes,
        ];
    }
}
