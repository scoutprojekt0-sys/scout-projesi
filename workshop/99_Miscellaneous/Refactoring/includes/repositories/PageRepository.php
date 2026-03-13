<?php

class PageRepository
{
    /** @var mysqli */
    private $databaseConnection;

    public function __construct($databaseConnection)
    {
        $this->databaseConnection = $databaseConnection;
    }

    public function getNavigationPages()
    {
        $statement = $this->databaseConnection->prepare('SELECT id, menulabel FROM pages ORDER BY id ASC');
        $statement->execute();
        $this->assertStatement($statement);

        $statement->bind_result($id, $menuLabel);
        $pages = array();
        while ($statement->fetch()) {
            $pages[] = array(
                'id' => (int) $id,
                'menulabel' => (string) $menuLabel,
            );
        }

        $statement->close();
        return $pages;
    }

    public function getPageById($id)
    {
        $statement = $this->databaseConnection->prepare('SELECT id, menulabel, content FROM pages WHERE id = ? LIMIT 1');
        $statement->bind_param('i', $id);
        $statement->execute();
        $this->assertStatement($statement);

        $statement->bind_result($pageId, $menuLabel, $content);
        $page = null;
        if ($statement->fetch()) {
            $page = array(
                'id' => (int) $pageId,
                'menulabel' => (string) $menuLabel,
                'content' => (string) $content,
            );
        }

        $statement->close();
        return $page;
    }

    public function createPage($menuLabel, $content)
    {
        $statement = $this->databaseConnection->prepare('INSERT INTO pages (menulabel, content) VALUES (?, ?)');
        $statement->bind_param('ss', $menuLabel, $content);
        $statement->execute();
        $this->assertStatement($statement);

        $affectedRows = $statement->affected_rows;
        $statement->close();
        return $affectedRows === 1;
    }

    public function createPageWithId($menuLabel, $content)
    {
        $statement = $this->databaseConnection->prepare('INSERT INTO pages (menulabel, content) VALUES (?, ?)');
        $statement->bind_param('ss', $menuLabel, $content);
        $statement->execute();
        $this->assertStatement($statement);

        $affectedRows = $statement->affected_rows;
        $insertId = $statement->insert_id;
        $statement->close();

        if ($affectedRows !== 1) {
            return null;
        }

        return (int) $insertId;
    }

    public function updatePage($pageId, $menuLabel, $content)
    {
        $statement = $this->databaseConnection->prepare('UPDATE pages SET menulabel = ?, content = ? WHERE id = ?');
        $statement->bind_param('ssi', $menuLabel, $content, $pageId);
        $statement->execute();
        $this->assertStatement($statement);

        $affectedRows = $statement->affected_rows;
        $statement->close();
        return $affectedRows >= 0;
    }

    public function deletePage($pageId)
    {
        $statement = $this->databaseConnection->prepare('DELETE FROM pages WHERE id = ?');
        $statement->bind_param('i', $pageId);
        $statement->execute();
        $this->assertStatement($statement);

        $affectedRows = $statement->affected_rows;
        $statement->close();
        return $affectedRows === 1;
    }

    public function pageExists($pageId)
    {
        $statement = $this->databaseConnection->prepare('SELECT id FROM pages WHERE id = ? LIMIT 1');
        $statement->bind_param('i', $pageId);
        $statement->execute();
        $this->assertStatement($statement);
        $statement->store_result();

        $exists = $statement->num_rows === 1;
        $statement->close();
        return $exists;
    }

    private function assertStatement($statement)
    {
        if ($statement->error) {
            app_fail('Veritabani islemi tamamlanamadi.', 'Database query failed: ' . $statement->error);
        }
    }
}
