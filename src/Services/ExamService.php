<?php

namespace App\Services;

use App\Core\Api;
use App\Core\Logger;

class ExamService {
    private Api $api;
    private Logger $logger;
    
    public function __construct(Api $api, Logger $logger) {
        $this->api = $api;
        $this->logger = $logger;
    }
    
    public function getExams(bool $isFinished = false): array {
        $response = $this->api->post('app/trainExamPlan/queryExamPlans', [
            'isFinished' => $isFinished ? '1' : '0',
            'isMy' => '1',
            'appPageNum' => 1,
            'appPageSize' => 10,
            'total' => 0
        ]);
        
        if (isset($response['code']) && $response['code'] === 500) {
            $this->logger->log("Failed to get exams: " . ($response['msg'] ?? 'Unknown error'), 'error');
            return [];
        }
        
        return $response['rows'] ?? [];
    }
    
    public function processExam(array $exam): bool {
        if (!isset($exam['exaName']) || !isset($exam['exaPlanId'])) {
            $this->logger->log("Invalid exam data", 'error');
            return false;
        }
        
        $examName = $exam['exaName'];
        $this->logger->log("Starting exam: {$examName}", 'info');
        
        // Get exam questions
        $questions = $this->getExamQuestions($exam);
        if (empty($questions)) {
            return false;
        }
        
        // Answer each question
        foreach ($questions as $index => $question) {
            if (!$this->answerQuestion($exam['exaPlanId'], $question, $index + 1)) {
                return false;
            }
        }
        
        // Submit exam
        return $this->submitExam($exam['exaPlanId']);
    }
    
    private function getExamQuestions(array $exam): array {
        $response = $this->api->post('app/trainExamPlan/startExamPlan', [
            'reExaTimeSec' => '1800',
            'isFinished' => $exam['isFinished'] ?? '0',
            'isPass' => $exam['isPass'] ?? '0',
            'exaTypeCode' => $exam['exaTypeCode'] ?? '',
            'exaCount' => $exam['exaCount'] ?? 0,
            'exaPlanId' => $exam['exaPlanId']
        ]);
        
        if (isset($response['code']) && $response['code'] === 500) {
            $this->logger->log("Failed to get exam questions: " . ($response['msg'] ?? 'Unknown error'), 'error');
            return [];
        }
        
        return $response['userSubjects'] ?? [];
    }
    
    private function answerQuestion(string $exaPlanId, array $question, int $questionNumber): bool {
        $response = $this->api->post('app/trainExamPlan/startAnswer', [
            'reExaTimeSec' => 1800 - $questionNumber * 30,
            'exaPlanId' => $exaPlanId,
            'isTrue' => '1',
            'subId' => $question['subId'],
            'subChoose' => $question['subTrueAnswer'],
            'sortNum' => $question['sortNum']
        ]);
        
        $success = $response === 2;
        $logType = $success ? 'success' : 'error';
        $this->logger->log(
            "Question {$questionNumber} " . ($success ? 'answered successfully' : 'failed'),
            $logType
        );
        
        return $success;
    }
    
    private function submitExam(string $exaPlanId): bool {
        $response = $this->api->post('app/trainExamPlan/startFinalAnswer', [
            'exaPlanId' => $exaPlanId
        ]);
        
        if (!isset($response['score'])) {
            $this->logger->log("Failed to submit exam: No score received", 'error');
            return false;
        }
        
        $score = intval($response['score']);
        $passed = $score >= 90;
        
        $this->logger->log(
            "Exam " . ($passed ? 'passed' : 'failed') . " with score: {$score}",
            $passed ? 'success' : 'error'
        );
        
        return $passed;
    }
} 