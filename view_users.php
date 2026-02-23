<?php
/**
 * Simple page to view users (staff) from the database.
 * Open in browser: http://localhost/mfs/demo.pradtec/view_users.php
 */
require __DIR__ . "/core/functions.php";

$db = new DBO();
$cid = CLIENT_ID;
$tbl = "org" . $cid . "_staff";

$users = $db->query(2, "SELECT `id`, `name`, `jobno`, `position`, `contact`, `status`, `branch` FROM `$tbl` ORDER BY `name` ASC");

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Users (Staff)</title>
	<style>
		body { font-family: Segoe UI, Arial, sans-serif; margin: 20px; background: #f5f5f5; }
		h1 { color: #074E8F; }
		table { border-collapse: collapse; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
		th, td { border: 1px solid #ddd; padding: 10px 14px; text-align: left; }
		th { background: #074E8F; color: #fff; }
		tr:nth-child(even) { background: #f9f9f9; }
		.count { color: #666; margin-bottom: 12px; }
		.empty { padding: 20px; color: #666; }
	</style>
</head>
<body>
	<h1>Users (Staff)</h1>
	<p class="count">Database: <strong>mfi_defined</strong> &bull; Table: <strong><?php echo htmlspecialchars($tbl); ?></strong></p>

	<?php if ($users && count($users) > 0): ?>
		<table>
			<thead>
				<tr>
					<th>#</th>
					<th>Name</th>
					<th>Job No</th>
					<th>Position</th>
					<th>Contact</th>
					<th>Branch</th>
					<th>Status</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($users as $i => $u): ?>
					<tr>
						<td><?php echo (int)$u['id']; ?></td>
						<td><?php echo htmlspecialchars($u['name'] ?? ''); ?></td>
						<td><?php echo htmlspecialchars($u['jobno'] ?? ''); ?></td>
						<td><?php echo htmlspecialchars($u['position'] ?? ''); ?></td>
						<td><?php echo htmlspecialchars($u['contact'] ?? ''); ?></td>
						<td><?php echo htmlspecialchars($u['branch'] ?? ''); ?></td>
						<td><?php echo (isset($u['status']) && $u['status'] === '0') ? 'Active' : htmlspecialchars($u['status'] ?? ''); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p class="count"><strong><?php echo count($users); ?></strong> user(s) found.</p>
	<?php else: ?>
		<p class="empty">No users found in <strong><?php echo htmlspecialchars($tbl); ?></strong>. Add staff from the app or insert rows in phpMyAdmin.</p>
	<?php endif; ?>
</body>
</html>
