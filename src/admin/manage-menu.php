<h2>Current Menu Items</h2>
    <table>
        <tr>
            <th>Name</th>
            <th>Description</th>
            <th>Price</th>
            <th>Actions</th>
        </tr>
        <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
            <td><?php echo htmlspecialchars($row['name']); ?></td>
            <td><?php echo htmlspecialchars($row['description']); ?></td>
            <td><?php echo htmlspecialchars($row['price']); ?></td>
            <td>
                <form action="manage-menu.php" method="POST" style="display:inline;">
                    <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                    <button type="submit" name="delete_item">Delete</button>
                </form>
                <button onclick="editItem(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['name']); ?>', '<?php echo htmlspecialchars($row['description']); ?>', <?php echo $row['price']; ?>)">Edit</button>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
</main>
<footer>
    <p>&copy; 2023 Your Company</p>
</footer>
<script src="../scripts/admin.js"></script>