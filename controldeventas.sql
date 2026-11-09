/* Base de datos sistemaweb para Microsoft SQL Server. */
IF DB_ID(N'sistemaweb') IS NULL
    EXEC(N'CREATE DATABASE [sistemaweb]');
GO

USE [sistemaweb];
GO

/* Evita saltos grandes de IDENTITY cuando SQL Server se reinicia inesperadamente. */
ALTER DATABASE SCOPED CONFIGURATION SET IDENTITY_CACHE = OFF;
GO

/* Creación de tabla usuarios. */
IF OBJECT_ID(N'dbo.usuarios', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.usuarios (
        id INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_usuarios PRIMARY KEY,
        username VARCHAR(20) NULL,
        [password] VARCHAR(255) NULL
    );
END;
GO

/* Amplía la columna para que pueda almacenar hashes de contraseña. */
IF COL_LENGTH(N'dbo.usuarios', N'password') < 255
    ALTER TABLE dbo.usuarios ALTER COLUMN [password] VARCHAR(255) NULL;
GO

/* Usuario inicial: admin / admin (hash compatible con password_verify de PHP). */
IF NOT EXISTS (SELECT 1 FROM dbo.usuarios WHERE username = 'admin')
    INSERT INTO dbo.usuarios (username, [password]) VALUES ('admin', '$2y$12$ZcjO6cVcXblTHogWoyeq2eVNbaZzPv98E.VXI/yYFJyxldI/.S04i');
GO

/* Creación de tabla ventas. */
IF OBJECT_ID(N'dbo.ventas', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.ventas (
        id INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_ventas PRIMARY KEY,
        num_ventas INT NULL,
        total_ventas DECIMAL(15,2) NULL,
        fecha DATE NULL
    );
END;
GO

/* Historial independiente: conserva los datos aunque la venta se elimine. */
IF OBJECT_ID(N'dbo.historial_ventas', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.historial_ventas (
        id BIGINT IDENTITY(1,1) NOT NULL CONSTRAINT PK_historial_ventas PRIMARY KEY,
        venta_id INT NOT NULL,
        accion VARCHAR(20) NOT NULL,
        fecha_cambio DATETIME2(0) NOT NULL CONSTRAINT DF_historial_ventas_fecha DEFAULT SYSDATETIME(),
        usuario_id INT NULL,
        usuario VARCHAR(100) NULL,
        datos_anteriores NVARCHAR(MAX) NULL,
        datos_nuevos NVARCHAR(MAX) NULL
    );
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.historial_ventas')
      AND name = N'IX_historial_ventas_venta_id'
)
    CREATE INDEX IX_historial_ventas_venta_id ON dbo.historial_ventas (venta_id, id DESC);
GO

/* Registros de ejemplo, se agregan solo si la tabla está vacía. */
IF NOT EXISTS (SELECT 1 FROM dbo.ventas)
BEGIN
    INSERT INTO dbo.ventas (num_ventas, total_ventas, fecha) VALUES (10, 6790, '2020-03-02');
    INSERT INTO dbo.ventas (num_ventas, total_ventas, fecha) VALUES (15, 9790, '2021-03-03');
    INSERT INTO dbo.ventas (num_ventas, total_ventas, fecha) VALUES (20, 12790, '2020-03-04');
    INSERT INTO dbo.ventas (num_ventas, total_ventas, fecha) VALUES (30, 15790, '2024-03-05');
    INSERT INTO dbo.ventas (num_ventas, total_ventas, fecha) VALUES (40, 21790, '2020-03-06');
END;
GO
