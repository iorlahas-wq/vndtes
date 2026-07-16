CREATE TABLE devices (

    device_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    device_code VARCHAR(20) NOT NULL UNIQUE,

    device_name VARCHAR(100) NOT NULL,

    device_type ENUM(

        'Router',
        'Switch',
        'Layer3 Switch',
        'PC',
        'Server',
        'Wireless Router',
        'Access Point',
        'Firewall',
        'Cloud'

    ) NOT NULL,

    vendor ENUM(

        'Cisco',
        'Juniper',
        'MikroTik',
        'Huawei',
        'Generic'

    ) DEFAULT 'Cisco',

    model VARCHAR(100),

    interface_count TINYINT UNSIGNED DEFAULT 2,

    icon VARCHAR(255),

    description TEXT,

    status ENUM(

        'Active',
        'Inactive'

    ) DEFAULT 'Active',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP

)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4;