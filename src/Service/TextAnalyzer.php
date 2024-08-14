<?php

namespace App\Service;

class TextAnalyzer
{
    /**
     * Find the top 3 most frequently occurring words in a given text, excluding banned words.
     *
     * @param string $text
     * @param array $banned
     * @return array
     */
    public function findTopWords(string $text, array $banned): array
    {
        $text = strtolower($text);
        $text = preg_replace('/[^\w\s]/', '', $text);

        $words = preg_split('/\s+/', $text);

        // Filter out banned words
        $bannedSet = array_flip($banned);
        $filteredWords = array_filter($words, function($word) use ($bannedSet) {
            return !isset($bannedSet[$word]);
        });

        // Count word frequencies
        $wordCount = array_count_values($filteredWords);

        // Sort by frequency and get top 3
        arsort($wordCount);
        return array_slice(array_keys($wordCount), 0, 3);
    }
}