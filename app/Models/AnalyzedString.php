<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyzedString extends Model
{
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    
    protected $fillable = [
        'id',
        'value',
        'length',
        'is_palindrome',
        'unique_characters',
        'word_count',
        'sha256_hash',
        'character_frequency_map'
    ];
    
    protected $casts = [
        'is_palindrome' => 'boolean',
        'character_frequency_map' => 'array'
    ];
    
    public static function analyzeString(string $value): array
    {
        $hash = hash('sha256', $value);
        $length = mb_strlen($value);
        
        // Case-insensitive palindrome check
        $lowerValue = strtolower($value);
        $reversedValue = strrev($lowerValue);
        $isPalindrome = $lowerValue === $reversedValue;
        
        $uniqueChars = count(array_unique(mb_str_split($value)));
        $wordCount = str_word_count($value);
        
        $charFreq = [];
        foreach (mb_str_split($value) as $char) {
            $charFreq[$char] = ($charFreq[$char] ?? 0) + 1;
        }
        
        return [
            'id' => $hash,
            'value' => $value,
            'length' => $length,
            'is_palindrome' => $isPalindrome,
            'unique_characters' => $uniqueChars,
            'word_count' => $wordCount,
            'sha256_hash' => $hash,
            'character_frequency_map' => $charFreq
        ];
    }
}
