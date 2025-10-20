<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnalyzedString;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class StringController extends Controller
{
    public function store(Request $request)
    {
        // Check if request has value field
        if (!$request->has('value')) {
            return response()->json(['error' => 'Missing value field'], 400);
        }
        
        // Check if value is string
        if (!is_string($request->input('value'))) {
            return response()->json(['error' => 'Value must be a string'], 422);
        }
        
        $value = $request->input('value');
        $hash = hash('sha256', $value);
        
        if (AnalyzedString::find($hash)) {
            return response()->json(['error' => 'String already exists'], 409);
        }
        
        $analysis = AnalyzedString::analyzeString($value);
        $analyzedString = AnalyzedString::create($analysis);
        
        return response()->json([
            'id' => $analyzedString->id,
            'value' => $analyzedString->value,
            'properties' => [
                'length' => $analyzedString->length,
                'is_palindrome' => $analyzedString->is_palindrome,
                'unique_characters' => $analyzedString->unique_characters,
                'word_count' => $analyzedString->word_count,
                'sha256_hash' => $analyzedString->sha256_hash,
                'character_frequency_map' => $analyzedString->character_frequency_map
            ],
            'created_at' => $analyzedString->created_at->toISOString()
        ], 201);
    }
    
    public function show(string $value)
    {
        $hash = hash('sha256', $value);
        $analyzedString = AnalyzedString::find($hash);
        
        if (!$analyzedString) {
            return response()->json(['error' => 'String not found'], 404);
        }
        
        return response()->json([
            'id' => $analyzedString->id,
            'value' => $analyzedString->value,
            'properties' => [
                'length' => $analyzedString->length,
                'is_palindrome' => $analyzedString->is_palindrome,
                'unique_characters' => $analyzedString->unique_characters,
                'word_count' => $analyzedString->word_count,
                'sha256_hash' => $analyzedString->sha256_hash,
                'character_frequency_map' => $analyzedString->character_frequency_map
            ],
            'created_at' => $analyzedString->created_at->toISOString()
        ]);
    }
    
    public function index(Request $request)
    {
        try {
            $query = AnalyzedString::query();
            $filters = [];
            
            if ($request->has('is_palindrome')) {
                $value = $request->is_palindrome;
                if (!in_array($value, ['true', 'false'])) {
                    return response()->json(['error' => 'Invalid query parameter values or types'], 400);
                }
                $isPalindrome = $value === 'true' ? 1 : 0;
                $query->where('is_palindrome', $isPalindrome);
                $filters['is_palindrome'] = (bool)$isPalindrome;
            }
            
            if ($request->has('min_length')) {
                $minLength = $request->min_length;
                if (!is_numeric($minLength) || $minLength < 0) {
                    return response()->json(['error' => 'Invalid query parameter values or types'], 400);
                }
                $minLength = (int) $minLength;
                $query->where('length', '>=', $minLength);
                $filters['min_length'] = $minLength;
            }
            
            if ($request->has('max_length')) {
                $maxLength = $request->max_length;
                if (!is_numeric($maxLength) || $maxLength < 0) {
                    return response()->json(['error' => 'Invalid query parameter values or types'], 400);
                }
                $maxLength = (int) $maxLength;
                $query->where('length', '<=', $maxLength);
                $filters['max_length'] = $maxLength;
            }
            
            if ($request->has('word_count')) {
                $wordCount = $request->word_count;
                if (!is_numeric($wordCount) || $wordCount < 0) {
                    return response()->json(['error' => 'Invalid query parameter values or types'], 400);
                }
                $wordCount = (int) $wordCount;
                $query->where('word_count', $wordCount);
                $filters['word_count'] = $wordCount;
            }
            
            if ($request->has('contains_character')) {
                $char = $request->contains_character;
                if (!is_string($char) || strlen($char) !== 1) {
                    return response()->json(['error' => 'Invalid query parameter values or types'], 400);
                }
                $query->whereRaw('character_frequency_map LIKE ?', ['%"' . $char . '":%']);
                $filters['contains_character'] = $char;
            }
            
            $strings = $query->get();
            
            return response()->json([
                'data' => $strings->map(function ($string) {
                    return [
                        'id' => $string->id,
                        'value' => $string->value,
                        'properties' => [
                            'length' => $string->length,
                            'is_palindrome' => $string->is_palindrome,
                            'unique_characters' => $string->unique_characters,
                            'word_count' => $string->word_count,
                            'sha256_hash' => $string->sha256_hash,
                            'character_frequency_map' => $string->character_frequency_map
                        ],
                        'created_at' => $string->created_at->toISOString()
                    ];
                }),
                'count' => $strings->count(),
                'filters_applied' => $filters
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid query parameter values or types'], 400);
        }
    }
    
    public function filterByNaturalLanguage(Request $request)
    {
        $query = urldecode($request->input('query', ''));
        
        if (empty($query)) {
            return response()->json(['error' => 'Unable to parse natural language query'], 400);
        }
        
        $filters = $this->parseNaturalLanguageQuery($query);
        
        if (empty($filters)) {
            return response()->json(['error' => 'Unable to parse natural language query'], 400);
        }
        
        try {
            $queryBuilder = AnalyzedString::query();
            
            foreach ($filters as $key => $value) {
                switch ($key) {
                    case 'is_palindrome':
                        $queryBuilder->where('is_palindrome', $value ? 1 : 0);
                        break;
                    case 'word_count':
                        $queryBuilder->where('word_count', $value);
                        break;
                    case 'min_length':
                        $queryBuilder->where('length', '>=', $value);
                        break;
                    case 'max_length':
                        $queryBuilder->where('length', '<=', $value);
                        break;
                    case 'contains_character':
                        $queryBuilder->whereRaw('character_frequency_map LIKE ?', ['%"' . $value . '":%']);
                        break;
                }
            }
            
            $strings = $queryBuilder->get();
            
            return response()->json([
                'data' => $strings->map(function ($string) {
                    return [
                        'id' => $string->id,
                        'value' => $string->value,
                        'properties' => [
                            'length' => $string->length,
                            'is_palindrome' => $string->is_palindrome,
                            'unique_characters' => $string->unique_characters,
                            'word_count' => $string->word_count,
                            'sha256_hash' => $string->sha256_hash,
                            'character_frequency_map' => $string->character_frequency_map
                        ],
                        'created_at' => $string->created_at->toISOString()
                    ];
                }),
                'count' => $strings->count(),
                'interpreted_query' => [
                    'original' => $query,
                    'parsed_filters' => $filters
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Query parsed but resulted in conflicting filters'], 422);
        }
    }
    
    public function destroy(string $value)
    {
        $hash = hash('sha256', $value);
        $analyzedString = AnalyzedString::find($hash);
        
        if (!$analyzedString) {
            return response()->json(['error' => 'String not found'], 404);
        }
        
        $analyzedString->delete();
        
        return response()->json(null, 204);
    }
    
    private function parseNaturalLanguageQuery(string $query): array
    {
        $filters = [];
        $query = strtolower(trim($query));
        
        // Palindrome detection
        if (preg_match('/palindrom/i', $query)) {
            $filters['is_palindrome'] = true;
        }
        
        // Word count detection - more specific patterns
        if (preg_match('/single word/', $query)) {
            $filters['word_count'] = 1;
        } elseif (preg_match('/one word/', $query)) {
            $filters['word_count'] = 1;
        } elseif (preg_match('/(\d+)\s+words?/', $query, $matches)) {
            $filters['word_count'] = (int)$matches[1];
        }
        
        // Length detection
        if (preg_match('/longer than (\d+)/', $query, $matches)) {
            $filters['min_length'] = (int)$matches[1] + 1;
        }
        if (preg_match('/shorter than (\d+)/', $query, $matches)) {
            $filters['max_length'] = (int)$matches[1] - 1;
        }
        if (preg_match('/more than (\d+) characters?/', $query, $matches)) {
            $filters['min_length'] = (int)$matches[1] + 1;
        }
        if (preg_match('/at least (\d+) characters?/', $query, $matches)) {
            $filters['min_length'] = (int)$matches[1];
        }
        
        // Character detection - improved patterns
        if (preg_match('/containing.*letter ([a-z])/i', $query, $matches)) {
            $filters['contains_character'] = strtolower($matches[1]);
        } elseif (preg_match('/contain.*letter ([a-z])/i', $query, $matches)) {
            $filters['contains_character'] = strtolower($matches[1]);
        } elseif (preg_match('/with.*letter ([a-z])/i', $query, $matches)) {
            $filters['contains_character'] = strtolower($matches[1]);
        } elseif (preg_match('/first vowel/', $query)) {
            $filters['contains_character'] = 'a';
        } elseif (preg_match('/letter ([a-z])/', $query, $matches)) {
            $filters['contains_character'] = strtolower($matches[1]);
        }
        
        return $filters;
    }
}
