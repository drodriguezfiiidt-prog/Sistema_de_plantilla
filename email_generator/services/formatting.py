"""Utilidades de formato numerico con separadores venezolanos."""

from decimal import Decimal, InvalidOperation, ROUND_HALF_UP

CENTS = Decimal("0.01")


def to_decimal(value) -> Decimal:
    """Convierte cualquier valor numerico a Decimal con dos decimales."""

    if isinstance(value, Decimal):
        return value.quantize(CENTS, rounding=ROUND_HALF_UP)
    try:
        return Decimal(str(value)).quantize(CENTS, rounding=ROUND_HALF_UP)
    except (InvalidOperation, TypeError, ValueError):
        return Decimal("0.00")


def money(value) -> str:
    """Devuelve el monto en formato 1.234,56."""

    amount = to_decimal(value)
    formatted = f"{amount:,.2f}"
    return formatted.translate(str.maketrans({",": ".", ".": ","}))


def price_cell(value) -> str:
    """Precio tal como aparece en la tabla (``80`` para 80,00; ``12,50``)."""

    amount = to_decimal(value)
    if amount == amount.to_integral_value():
        return str(int(amount))
    return money(amount)


def money_usd(value) -> str:
    """Devuelve el monto con la moneda, por ejemplo ``10,00 USD``."""

    return f"{money(value)} USD"


