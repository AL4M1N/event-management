<?php

namespace App\Controllers\Admin;

use App\Models\Attendee;
use App\Models\Event;
use App\Helpers\Helper;
use InvalidArgumentException;

class AttendeesController
{
    private $attendeeModel;
    private $eventModel;

    private const PER_PAGE = 5;
    private const MINIMUM_NAME_LENGTH = 3;
    private const PHONE_PATTERN = '/^01[0-9]{9}$/';
    private const NID_PATTERN = '/^[0-9]{10,17}$/';

    public function __construct(Attendee $attendeeModel, ?Event $eventModel = null)
    {
        $this->attendeeModel = $attendeeModel;
        $this->eventModel = $eventModel;
    }

    public function index()
    {
        $currentPage = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
        $offset = ($currentPage - 1) * self::PER_PAGE;
        
        $filters = $this->buildSearchFilters();
        $filterQuery = $filters['query'];
        $params = $filters['params'];

        $totalRecords = $this->attendeeModel->getTotalRecords($filterQuery, $params);
        $totalPages = ceil($totalRecords / self::PER_PAGE);
        $attendees = $this->attendeeModel->getAttendees($filterQuery, $params, $offset, self::PER_PAGE);
        $perPage = self::PER_PAGE;

        return Helper::view('backend/attendees/index.php', 
            compact('attendees', 'totalPages', 'currentPage', 'perPage')
        );
    }

    private function buildSearchFilters(): array
    {
        $searchFields = ['name', 'phone', 'nid'];
        $filters = [];
        $params = [];

        foreach ($searchFields as $field) {
            if ($value = trim(filter_input(INPUT_GET, $field))) {
                $filters[] = "$field LIKE :$field";
                $params[":$field"] = "%{$value}%";
            }
        }

        return [
            'query' => $filters ? 'WHERE ' . implode(' AND ', $filters) : '',
            'params' => $params
        ];
    }

    public function showCreateAttendeeForm()
    {
        return Helper::view('backend/attendees/create.php');
    }

    public function store()
    {
        if (!Helper::isPostRequest()) {
            return Helper::redirect('attendees/create');
        }

        $formData = $this->sanitizeAttendeeData($_POST);
        $errors = $this->validateAttendeeData($formData);

        if (!empty($errors)) {
            return Helper::view('backend/attendees/create.php', ['errors' => $errors, 'formData' => $formData]);
        }

        if ($this->attendeeModel->getAttendeeByNid($formData['nid'])) {
            return Helper::view('backend/attendees/create.php', [
                'error' => 'An attendee with this NID already exists.',
                'formData' => $formData
            ]);
        }

        try {
            $this->attendeeModel->createAttendee($formData);
            return Helper::redirect('attendees', [
                'success' => 'Attendee created successfully!'
            ]);
        } catch (\Exception $e) {
            return Helper::view('backend/attendees/create.php', [
                'error' => 'An error occurred while saving the attendee.',
                'formData' => $formData
            ]);
        }
    }

    public function showEditAttendeeForm($id)
    {
        $attendee = $this->attendeeModel->getAttendeeById($id);
        return Helper::view('backend/attendees/edit.php', compact('attendee'));
    }

    public function update($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return Helper::redirect('attendees/edit/' . $id);
        }

        $formData = [
            'name' => trim($_POST['name']),
            'nid' => trim($_POST['nid']),
            'phone' => trim($_POST['phone'])
        ];

        $errors = $this->validateAttendeeData($formData);
        $attendee = $this->attendeeModel->getAttendeeById($id);
        $error = null;
        $success = null;

        if (!empty($errors)) {
            $error = 'Please correct the errors below.';
            return Helper::view('backend/attendees/edit.php', 
                compact('error', 'errors', 'attendee', 'formData')
            );
        }

        $result = $this->attendeeModel->update($id, $formData);

        if ($result) {
            $success = 'Attendee updated successfully! <a href="' . Helper::baseUrl('attendees') . '">View Attendees</a>';
        } else {
            $error = 'An error occurred while updating the attendee.';
        }

        return Helper::view('backend/attendees/edit.php', 
            compact('success', 'error', 'attendee', 'formData')
        );
    }

    public function delete($id)
    {
        $this->attendeeModel->delete($id);
        return Helper::redirect('attendees');
    }

    public function registerAttendee($eventId)
    {
        if (!Helper::isPostRequest()) {
            return Helper::jsonResponse(['success' => false, 'message' => 'Invalid request method'], 405);
        }

        try {
            $formData = $this->validateEventRegistration($eventId, $_POST);
            $result = $this->attendeeModel->registerAttendee($formData);
            
            if ($result['success']) {
                $newAttendee = $this->attendeeModel->getAttendeeById($result['attendee_id']);
                return Helper::jsonResponse([
                    'success' => true,
                    'message' => 'Registration successful!',
                    'attendee' => $this->formatAttendeeResponse($newAttendee)
                ]);
            }
        } catch (InvalidArgumentException $e) {
            return Helper::jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return Helper::jsonResponse([
                'success' => false,
                'message' => 'An error occurred during registration.'
            ], 500);
        }
    }

    private function validateEventRegistration($eventId, array $data): array
    {
        $formData = $this->sanitizeAttendeeData($data);
        $formData['event_id'] = $eventId;

        $errors = $this->validateAttendeeData($formData);
        if (!empty($errors)) {
            throw new InvalidArgumentException('Validation failed');
        }

        if ($this->attendeeModel->checkDuplicateRegistration($eventId, $formData['nid'])) {
            throw new InvalidArgumentException('Already registered for this event');
        }

        $event = $this->eventModel->getEventById($eventId);
        $attendees = $this->attendeeModel->getEventAttendees($eventId);
        if (count($attendees) >= $event['capacity']) {
            throw new InvalidArgumentException('Event has reached maximum capacity');
        }

        return $formData;
    }

    private function sanitizeAttendeeData(array $data): array
    {
        return array_map('trim', [
            'name' => $data['name'] ?? '',
            'phone' => $data['phone'] ?? '',
            'nid' => $data['nid'] ?? ''
        ]);
    }

    private function formatAttendeeResponse(array $attendee): array
    {
        return [
            'name' => $attendee['name'],
            'phone' => $attendee['phone'],
            'nid' => $attendee['nid'],
        ];
    }

    private function validateAttendeeData($data)
    {
        $errors = [];

        if (strlen($data['name']) < self::MINIMUM_NAME_LENGTH) {
            $errors['name'] = 'Name must be at least ' . self::MINIMUM_NAME_LENGTH . ' characters long.';
        }

        if (!preg_match(self::PHONE_PATTERN, $data['phone'])) {
            $errors['phone'] = 'Phone number must start with 01 and be exactly 11 digits.';
        }

        if (!preg_match(self::NID_PATTERN, $data['nid'])) {
            $errors['nid'] = 'Please enter a valid NID number (10-17 digits).';
        }

        return $errors;
    }

    public function addExistingAttendee($eventId)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Helper::jsonResponse(['success' => false, 'message' => 'Invalid request method']);
            return;
        }

        $json = json_decode(file_get_contents('php://input'), true);
        $attendeeId = $json['attendee_id'] ?? null;

        if (!$attendeeId) {
            Helper::jsonResponse(['success' => false, 'message' => 'Attendee ID is required']);
            return;
        }

        $event = $this->eventModel->getEventById($eventId);
        if (!$event) {
            Helper::jsonResponse(['success' => false, 'message' => 'Event not found']);
            return;
        }

        $currentAttendees = $this->attendeeModel->getEventAttendees($eventId);
        if (count($currentAttendees) >= $event['capacity']) {
            Helper::jsonResponse([
                'success' => false,
                'message' => 'Sorry, this event has reached its maximum capacity.'
            ]);
            return;
        }

        $attendee = $this->attendeeModel->getAttendeeById($attendeeId);
        if (!$attendee) {
            Helper::jsonResponse(['success' => false, 'message' => 'Attendee not found']);
            return;
        }

        if ($this->attendeeModel->checkDuplicateRegistration($eventId, $attendee['nid'])) {
            Helper::jsonResponse([
                'success' => false,
                'message' => 'This attendee is already registered for this event'
            ]);
            return;
        }

        $result = $this->attendeeModel->addExistingAttendee($eventId, $attendeeId);

        if ($result['success']) {
            $attendee = $this->attendeeModel->getAttendeeById($attendeeId);
            Helper::jsonResponse([
                'success' => true,
                'message' => 'Attendee added successfully',
                'attendee' => [
                    'name' => $attendee['name'],
                    'phone' => $attendee['phone'],
                    'nid' => $attendee['nid']
                ]
            ]);
        } else {
            Helper::jsonResponse([
                'success' => false,
                'message' => $result['error'] ?? 'Failed to add attendee'
            ]);
        }
    }

    public function listAvailableAttendees($eventId)
    {
        try {
            $attendees = $this->attendeeModel->getAvailableAttendees($eventId);
            Helper::jsonResponse([
                'success' => true,
                'data' => $attendees
            ]);
        } catch (\Exception $e) {
            Helper::jsonResponse([
                'success' => false,
                'message' => 'Failed to fetch available attendees'
            ]);
        }
    }
}