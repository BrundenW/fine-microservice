<?php
declare(strict_types=1);

namespace App\Controller;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class FineController
{
    /**
     * @param PDO $pdo Database connection instance
     */
    public function __construct(private PDO $pdo) {}

    /**
     * Apply overdue penalties of 20% to all eligible fines.
     */
    private function applyOverduePenalties(): void
    {
        $sql = "UPDATE fines
                SET status = 'overdue',
                    fine_amount = ROUND(fine_amount * 1.20, 2)
                WHERE status <> 'paid'
                  AND status <> 'overdue'
                  AND date_issued <= (CURRENT_DATE - INTERVAL '30 days')";
        $this->pdo->exec($sql);
    }

    /**
     * List all fines with pagination.
     * @param Request $request The request object
     * @param Response $response The response object
     * @return Response JSON response containing list of fines
     */
    public function list(Request $request, Response $response): Response
    {
       $this->applyOverduePenalties();

       $params = $request->getQueryParams();
       $limit = isset($params['limit']) ? max(1, min(100, (int)$params['limit'])) : 50;
       $offset = isset($params['offset']) ? max(0, (int)$params['offset']) : 0;

       $stmt = $this->pdo->prepare('SELECT * FROM fines ORDER BY fine_id DESC LIMIT :limit OFFSET :offset');
       $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
       $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
       $stmt->execute();
       $rows = $stmt->fetchAll();

       $response->getBody()->write(json_encode(['data' => $rows, 'limit' => $limit, 'offset' => $offset]));
       return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Get a specific fine by ID
     * @param Request $request The request object
     * @param Response $response The response object
     * @param array $args Route arguments
     * @return Response JSON response containing fine details or error
     */
    public function get(Request $request, Response $response, array $args): Response
    {
        $this->applyOverduePenalties();

        $id = (int)$args['id'];
        $stmt = $this->pdo->prepare('SELECT * FROM fines WHERE fine_id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            $response->getBody()->write(json_encode(['error' => 'Not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }
        $response->getBody()->write(json_encode($row));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Create a new fine
     * @param Request $request The request object with fine details
     * @param Response $response The response object
     * @return Response JSON response containing created fine or error
     */
    public function create(Request $request, Response $response): Response
    {
        $data = (array)$request->getParsedBody();
        $required = ['offender_name', 'offence_type', 'fine_amount', 'date_issued'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                $response->getBody()->write(json_encode(['error' => "Missing field: $field"]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
        }

        $status = $data['status'] ?? 'unpaid';

        // Frequent offender surcharge
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) AS cnt FROM fines WHERE offender_name = :offender_name AND status <> 'paid'");
        $countStmt->execute([':offender_name' => $data['offender_name']]);
        $count = (int)($countStmt->fetch()['cnt'] ?? 0);
        $fineAmount = (float)$data['fine_amount'];
        if ($count >= 3) {
            $fineAmount += 50.0;
        }

        $sql = 'INSERT INTO fines (offender_name, offence_type, fine_amount, date_issued, status)
                VALUES (:offender_name, :offence_type, :fine_amount, :date_issued, :status)
                RETURNING *';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':offender_name' => $data['offender_name'],
            ':offence_type'  => $data['offence_type'],
            ':fine_amount'   => $fineAmount,
            ':date_issued'   => $data['date_issued'],
            ':status'        => $status,
        ]);

        $created = $stmt->fetch();

        $response->getBody()->write(json_encode($created));
        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
    }

    /**
     * Update all fields of an existing fine
     * @param Request $request The request object with updated fine details
     * @param Response $response The response object
     * @param array $args Route arguments
     * @return Response JSON response containing updated fine or error
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $data = (array)$request->getParsedBody();

        $required = ['offender_name', 'offence_type', 'fine_amount', 'date_issued', 'status'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                $response->getBody()->write(json_encode(['error' => "Missing field: $field"]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
        }

        $sql = 'UPDATE fines SET offender_name = :offender_name, offence_type = :offence_type, fine_amount = :fine_amount, date_issued = :date_issued, status = :status WHERE fine_id = :id RETURNING *';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':offender_name' => $data['offender_name'],
            ':offence_type'  => $data['offence_type'],
            ':fine_amount'   => $data['fine_amount'],
            ':date_issued'   => $data['date_issued'],
            ':status'        => $data['status'],
            ':id'            => $id,
        ]);

        $updated = $stmt->fetch();
        if (!$updated) {
            $response->getBody()->write(json_encode(['error' => 'Not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode($updated));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Partially update an existing fine
     * @param Request $request The request object with fields to update
     * @param Response $response The response object
     * @param array $args Route arguments
     * @return Response JSON response containing updated fine or error
     */
    public function partialUpdate(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $data = (array)$request->getParsedBody();

        $fields = array_intersect_key($data, array_flip(['offender_name', 'offence_type', 'fine_amount', 'date_issued', 'status']));
        if (empty($fields)) {
            $response->getBody()->write(json_encode(['error' => 'No fields to update']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $sets = [];
        $params = [':id' => $id];
        foreach ($fields as $k => $v) {
            $param = ':' . $k;
            $sets[] = "$k = $param";
            $params[$param] = $v;
        }

        $sql = 'UPDATE fines SET ' . implode(', ', $sets) . ' WHERE fine_id = :id RETURNING *';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $updated = $stmt->fetch();
        if (!$updated) {
            $response->getBody()->write(json_encode(['error' => 'Not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode($updated));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Delete a fine by ID
     * @param Request $request The request object
     * @param Response $response The response object
     * @param array $args Route arguments
     * @return Response Empty response with 204 status or error
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $stmt = $this->pdo->prepare('DELETE FROM fines WHERE fine_id = :id');
        $stmt->execute([':id' => $id]);

        if ($stmt->rowCount() === 0) {
            $response->getBody()->write(json_encode(['error' => 'Not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        return $response->withStatus(204);
    }

    /**
     * Mark a fine as paid
     * @param Request $request The request object
     * @param Response $response The response object
     * @param array $args Route arguments
     * @return Response Empty response with 204 status or error
     */
    public function markAsPaid(Request $request, Response $response, array $args): Response
    {
        $this->applyOverduePenalties();

        $id = (int)$args['id'];

        $sql = "UPDATE fines
                SET status = 'paid',
                    fine_amount = CASE
                        WHEN CURRENT_DATE <= (date_issued + INTERVAL '14 days') THEN ROUND(fine_amount * 0.90, 2)
                        ELSE fine_amount
                    END
                WHERE fine_id = :id AND status <> 'paid'
                RETURNING fine_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $updated = $stmt->fetch(\PDO::FETCH_ASSOC);

        // When fine successfully marked as paid
        if ($updated) {
            return $response->withStatus(204);
        }

        $check = $this->pdo->prepare("SELECT status FROM fines WHERE fine_id = :id");
        $check->execute([':id' => $id]);
        $row = $check->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            $response->getBody()->write(json_encode(['error' => 'Fine not found']));
            return $response
                ->withStatus(404)
                ->withHeader('Content-Type', 'application/json');
        }

        if ($row['status'] === 'paid') {
            $response->getBody()->write(json_encode(['error' => 'Fine already paid']));
            return $response
                ->withStatus(409)
                ->withHeader('Content-Type', 'application/json');
        }

        // Fallback (unexpected state)
        $response->getBody()->write(json_encode(['error' => 'Unable to mark as paid']));
        return $response
            ->withStatus(409)
            ->withHeader('Content-Type', 'application/json');
    }

}
