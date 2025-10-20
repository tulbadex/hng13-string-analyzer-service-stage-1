<?php

namespace Tests\Feature;

use App\Models\AnalyzedString;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StringAnalyzerTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_api_health_check()
    {
        $response = $this->getJson('/');
        
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'message',
                     'version',
                     'endpoints'
                 ]);
    }
    
    public function test_can_create_analyzed_string()
    {
        $response = $this->postJson('/strings', [
            'value' => 'hello world'
        ]);
        
        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'id',
                     'value',
                     'properties' => [
                         'length',
                         'is_palindrome',
                         'unique_characters',
                         'word_count',
                         'sha256_hash',
                         'character_frequency_map'
                     ],
                     'created_at'
                 ]);
    }
    
    public function test_cannot_create_duplicate_string()
    {
        $this->postJson('/strings', ['value' => 'test']);
        
        $response = $this->postJson('/strings', ['value' => 'test']);
        
        $response->assertStatus(409);
    }
    
    public function test_can_retrieve_string()
    {
        $this->postJson('/strings', ['value' => 'test']);
        
        $response = $this->getJson('/strings/test');
        
        $response->assertStatus(200)
                 ->assertJson(['value' => 'test']);
    }
    
    public function test_can_filter_strings()
    {
        $this->postJson('/strings', ['value' => 'racecar']);
        $this->postJson('/strings', ['value' => 'hello']);
        
        $response = $this->getJson('/strings?is_palindrome=true');
        
        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data');
    }
    
    public function test_can_delete_string()
    {
        $this->postJson('/strings', ['value' => 'test']);
        
        $response = $this->deleteJson('/strings/test');
        
        $response->assertStatus(204);
    }
}
