<?php

if (isset($query) && count($query) > 0) {
    foreach ($query as $row) {
        echo "<b>" . esc($row['user']) . "</b>:";
        echo " " . esc($row['msg']) . " </br>";
    }
}

// Display pagination controls if pagination data is available
if (isset($pagination) && is_array($pagination)) {
    echo '<div class="pagination">';

    // Previous page link
    if ($pagination['hasPrev']) {
        echo '<a href="' . site_url('chat/html?page=' . ($pagination['page'] - 1) . '&per_page=' . $pagination['perPage']) . '" class="pagination-link">&laquo; Previous</a>';
    } else {
        echo '<span class="pagination-link disabled">&laquo; Previous</span>';
    }

    // Page numbers
    echo '<span class="pagination-info">Page ' . $pagination['page'] . ' of ' . $pagination['totalPages'] . '</span>';

    // Next page link
    if ($pagination['hasNext']) {
        echo '<a href="' . site_url('chat/html?page=' . ($pagination['page'] + 1) . '&per_page=' . $pagination['perPage']) . '" class="pagination-link">Next &raquo;</a>';
    } else {
        echo '<span class="pagination-link disabled">Next &raquo;</span>';
    }

    echo '</div>';

    // Add some basic CSS for pagination
    echo '<style>
        .pagination {
            margin-top: 20px;
            text-align: center;
        }
        .pagination-link {
            margin: 0 5px;
            padding: 5px 10px;
            text-decoration: none;
            border: 1px solid #ddd;
            color: #333;
            border-radius: 3px;
        }
        .pagination-link:hover {
            background-color: #f5f5f5;
        }
        .pagination-info {
            margin: 0 10px;
        }
        .disabled {
            color: #aaa;
            border-color: #eee;
            cursor: not-allowed;
        }
    </style>';
}
