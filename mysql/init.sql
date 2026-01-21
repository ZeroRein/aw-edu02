-- データベースの作成
CREATE DATABASE IF NOT EXISTS koki04 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE koki04;

-- 1. ユーザーテーブル (users)
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    icon_filename VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. 投稿テーブル (bbs_entries)
CREATE TABLE IF NOT EXISTS bbs_entries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    body TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    -- 外部キー制約: ユーザーが消えたら投稿も消える
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. 投稿画像テーブル (entry_images)
-- ここで1つの投稿に対して複数の画像を管理します
CREATE TABLE IF NOT EXISTS entry_images (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entry_id INT UNSIGNED NOT NULL,
    image_filename VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    -- 外部キー制約: 投稿が消えたら画像データも消える
    FOREIGN KEY (entry_id) REFERENCES bbs_entries(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. フォロー関係テーブル (user_relationships)
-- タイムラインの表示ロジックに必要です
CREATE TABLE IF NOT EXISTS user_relationships (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    follower_user_id INT UNSIGNED NOT NULL, -- フォローする側
    followee_user_id INT UNSIGNED NOT NULL, -- フォローされる側
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    -- 重複フォローを防ぐユニーク制約
    UNIQUE KEY unique_relationship (follower_user_id, followee_user_id),
    FOREIGN KEY (follower_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (followee_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------
-- 動作確認用のダミーデータ (必要に応じて使ってください)
-- ---------------------------------------------

-- テストユーザーの作成 (パスワードは適当です。本番ではハッシュ化してください)
INSERT INTO users (name, email, password, icon_filename) VALUES 
('テストユーザー1', 'test1@example.com', 'password', NULL),
('テストユーザー2', 'test2@example.com', 'password', NULL);

-- テスト投稿
INSERT INTO bbs_entries (user_id, body) VALUES 
(1, 'ユーザー1の投稿です。'),
(2, 'ユーザー2の投稿です。');

-- ユーザー1がユーザー2をフォローする
INSERT INTO user_relationships (follower_user_id, followee_user_id) VALUES (1, 2);
