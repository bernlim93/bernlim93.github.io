<?php
/* Functions for sentiment analysis */

class SentimentAnalysis{

    public static function getSentimentScore($message)
    {
    	$totalValue = 0.0;
    	$totalCount = 0.0;
    	$words = explode(" ", $message);
    	$prefixModifier = 1.0;
    	$prefixScore = 0.0;
    	$sentiment = 0.0;
    	foreach($words as $word)
    	{
    		$totalCount += 1.0;
    		$query = DB::query("SELECT * FROM Words WHERE word = '".DB::esc($word)."'")->fetch_object();
    		$score = 0.0;
    		$abs_score = 0.0;
    		if($query){
    			$score = $query->score;
    		}
    		
    		if($score != 0.0 && $prefixModifier != 1.0) {
    			$totalValue += $prefixModifier * $score - $prefixScore;
    		}
    		else $totalValue += floatval($score);
    		
    		if($query)$prefixModifier = $query->prefix_modifier;
    		else $prefixModifier = 1.0;
    		
    		$prefixScore = $score;
    	}
    	
    	if($totalCount == 0.0)return 0.0;
    	if($totalCount == 0)return 0.0;
    	return $totalValue/$totalCount;
    }
}
?>